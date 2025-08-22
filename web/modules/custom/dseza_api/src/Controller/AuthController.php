<?php

namespace Drupal\dseza_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Drupal\user\Entity\User;

/**
 * OAuth2.1 Authorization Code + PKCE BFF endpoints.
 * Minimal scaffold: delegates to external IdP using env config.
 */
class AuthController extends ControllerBase {
  /**
   * GET /api/auth/login
   * Redirect user to IdP authorize endpoint with PKCE.
   */
  public function login(Request $request): Response {
    $config = \Drupal::config('system.site');
    $clientId = (string) ($config->get('oidc_client_id') ?? '');
    $authorizeUrl = rtrim((string) ($config->get('oidc_authorize_url') ?? ''), '/');
    $redirectUri = (string) ($config->get('oidc_redirect_uri') ?? '');
    // Allow overriding redirect_uri via query for flexibility between environments
    $overrideRedirect = $request->get('redirect_uri');
    if (!empty($overrideRedirect) && is_string($overrideRedirect)) {
      $redirectUri = $overrideRedirect;
    }
    if (!$clientId || !$authorizeUrl || !$redirectUri) {
      return new JsonResponse(['error' => 'OIDC is not configured'], 500);
    }
    // PKCE handled on frontend or via separate endpoint to mint/verifier in session.
    $state = bin2hex(random_bytes(16));
    $codeChallenge = $request->get('code_challenge');
    $codeChallengeMethod = $request->get('code_challenge_method', 'S256');

    // Internal vs external login flow via query parameter 'flow'
    $flow = (string) $request->get('flow');
    if ($flow === 'internal') {
      // Send users to Drupal's native login form for internal network
      return new RedirectResponse('/user/login');
    }
    // Optional: auto-route internal users by private IP ranges when flow not specified
    if ($flow === '' || $flow === 'auto') {
      $clientIp = $request->getClientIp() ?: '';
      if (preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/', $clientIp)) {
        return new RedirectResponse('/user/login');
      }
    }

    // Control re-authentication behavior at IdP.
    // Default to 'select_account' so users can choose another account when re-login.
    $prompt = (string) $request->get('prompt');
    if ($prompt === '') {
      $prompt = (string) (\Drupal::config('system.site')->get('oidc_prompt') ?? 'select_account');
    }

    $query = http_build_query([
      'response_type' => 'code',
      'client_id' => $clientId,
      'redirect_uri' => $redirectUri,
      'scope' => (string) ($config->get('oidc_scope') ?? 'openid profile email'),
      'state' => $state,
      'code_challenge' => $codeChallenge,
      'code_challenge_method' => $codeChallengeMethod,
      // OIDC optional prompt param: 'login' | 'consent' | 'select_account'.
      // Google honors 'select_account' and 'consent'.
      'prompt' => $prompt,
    ]);

    // Store state server-side if desired. Here we set a transient cookie.
    $response = new TrustedRedirectResponse($authorizeUrl . '?' . $query, 302);
    $cookieDomain = \Drupal::service('settings')->get('cookie_domain');
    $response->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie(
      'oidc_state', $state, time() + 600, '/', $cookieDomain ?: NULL, TRUE, TRUE, FALSE, 'None'
    ));
    // Persist desired post-login redirect target (frontend callback) if provided
    $postLoginRedirect = $request->get('redirect');
    if (is_string($postLoginRedirect) && $postLoginRedirect !== '') {
      $response->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie(
        'post_login_redirect', $postLoginRedirect, time() + 900, '/', $cookieDomain ?: NULL, TRUE, TRUE, FALSE, 'None'
      ));
    }
    return $response;
  }

  /**
   * GET /api/auth/callback
   * Exchange authorization code for tokens and set HttpOnly session cookie.
   */
  public function callback(Request $request): Response {
    $code = $request->get('code');
    $state = $request->get('state');
    if (!$code || !$state) {
      return new JsonResponse(['error' => 'invalid_callback'], 400);
    }

    $config = \Drupal::config('system.site');
    $tokenUrl = (string) ($config->get('oidc_token_url') ?? '');
    $clientId = (string) ($config->get('oidc_client_id') ?? '');
    $clientSecret = (string) ($config->get('oidc_client_secret') ?? '');
    $redirectUri = (string) ($config->get('oidc_redirect_uri') ?? '');
    if (!$tokenUrl || !$clientId || !$redirectUri) {
      return new JsonResponse(['error' => 'OIDC is not configured'], 500);
    }

    $codeVerifier = $request->get('code_verifier');
    $tokenRequest = [
      'grant_type' => 'authorization_code',
      'code' => $code,
      'redirect_uri' => $redirectUri,
      'client_id' => $clientId,
    ];
    // Include PKCE verifier if provided by the frontend
    if (!empty($codeVerifier)) {
      $tokenRequest['code_verifier'] = $codeVerifier;
    }
    // Include client_secret when configured (required by confidential clients like Google Web)
    if (!empty($clientSecret)) {
      $tokenRequest['client_secret'] = $clientSecret;
    }
    $body = http_build_query($tokenRequest);

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
    $err = curl_error($ch);
    curl_close($ch);

    if ($status < 200 || $status >= 300 || !$raw) {
      return new JsonResponse(['error' => 'token_exchange_failed', 'details' => $err ?: $raw], 502);
    }

    $payload = json_decode($raw, true) ?: [];

    // Derive user identity from id_token or userinfo
    $claims = [];
    if (!empty($payload['id_token'])) {
      // Decode JWT without verification for identity mapping
      $parts = explode('.', $payload['id_token']);
      if (count($parts) >= 2) {
        $json = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (is_array($json)) {
          $claims = $json;
        }
      }
    }

    if (empty($claims)) {
      // Fallback to userinfo endpoint when available
      $userinfoUrl = (string) ($config->get('oidc_userinfo_url') ?? '');
      if ($userinfoUrl && !empty($payload['access_token'])) {
        $ch2 = curl_init($userinfoUrl);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $payload['access_token']]);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        $rawUserinfo = curl_exec($ch2);
        curl_close($ch2);
        $ui = json_decode($rawUserinfo, true);
        if (is_array($ui)) {
          $claims = $ui;
        }
      }
    }

    // Map to Drupal user
    $email = isset($claims['email']) ? (string) $claims['email'] : NULL;
    $name = isset($claims['name']) ? (string) $claims['name'] : (isset($claims['preferred_username']) ? (string) $claims['preferred_username'] : NULL);
    $sub  = isset($claims['sub']) ? (string) $claims['sub'] : NULL;
    if (!$name) {
      $name = $email ?: ('user_' . substr(sha1((string) $sub), 0, 8));
    }

    // Load or create account
    $storage = \Drupal::entityTypeManager()->getStorage('user');
    $account = NULL;
    if ($email) {
      $candidates = $storage->loadByProperties(['mail' => $email]);
      $account = $candidates ? reset($candidates) : NULL;
    }
    if (!$account) {
      $candidates = $storage->loadByProperties(['name' => $name]);
      $account = $candidates ? reset($candidates) : NULL;
    }
    if (!$account) {
      $account = User::create([
        'name' => $name,
        'mail' => $email ?: ($sub ? $sub . '@example.local' : NULL),
        'status' => 1,
      ]);
      $account->save();
    }

    // Finalize Drupal login session so currentUser() is authenticated
    if ($account) {
      user_login_finalize($account);
    }

    // Set HttpOnly cookies; cookies must be cross-site for SPA frontend on another subdomain
    // Determine post-login redirect destination
    $postLoginRedirectCookie = (string) $request->cookies->get('post_login_redirect', '');
    $postLoginRedirect = $postLoginRedirectCookie !== '' ? $postLoginRedirectCookie : (string) ($config->get('oidc_post_login_redirect') ?? '/');
    $response = new TrustedRedirectResponse($postLoginRedirect ?: '/', 302);
    // SameSite=None; Secure for cross-site XHR from frontend
    $cookieParams = ['/', NULL, TRUE, TRUE, FALSE, 'None'];
    // Apply cookie domain when configured (e.g., .lndo.site) so cookies are sent to all subdomains
    $cookieDomain = \Drupal::service('settings')->get('cookie_domain');
    if ($cookieDomain) {
      $cookieParams[1] = $cookieDomain; // path '/', domain set below
    }
    if (!empty($payload['access_token'])) {
      $response->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie('access_token', $payload['access_token'], time() + 900, ...$cookieParams));
    }
    if (!empty($payload['refresh_token'])) {
      $response->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie('refresh_token', $payload['refresh_token'], time() + 60 * 60 * 24 * 7, ...$cookieParams));
    }
    // Clear the temporary redirect cookie
    $cookieDomain = \Drupal::service('settings')->get('cookie_domain');
    $response->headers->clearCookie('post_login_redirect', '/', $cookieDomain ?: NULL, TRUE, TRUE, 'None');
    return $response;
  }

  /**
   * POST /api/auth/logout
   * Clear cookies and optionally call IdP end-session.
   */
  public function logout(Request $request): Response {
    // 1) Terminate Drupal session so currentUser() is anonymous afterwards
    try {
      // Best-effort: invalidate active session and log the user out
      if ($request->hasSession()) {
        $request->getSession()->invalidate();
      }
      user_logout();
    }
    catch (\Throwable $e) {
      // Do not fail logout because of session exceptions
    }

    // 2) Prepare response and clear cookies
    $response = new JsonResponse(['status' => 'ok']);

    // Clear OAuth cookies with attributes matching creation (SameSite=None; Secure; domain if set)
    $cookieDomain = \Drupal::service('settings')->get('cookie_domain');
    $response->headers->clearCookie('access_token', '/', $cookieDomain ?: NULL, TRUE, TRUE, 'None');
    $response->headers->clearCookie('refresh_token', '/', $cookieDomain ?: NULL, TRUE, TRUE, 'None');

    // Also clear Drupal session cookie(s). Name can be dynamic (SSESS*). Use configured name.
    try {
      /** @var \Drupal\Core\Session\SessionConfigurationInterface $sessionConfig */
      $sessionConfig = \Drupal::service('session_configuration');
      $options = $sessionConfig->getOptions($request);
      $sessionCookieName = isset($options['name']) ? (string) $options['name'] : NULL;
      if ($sessionCookieName) {
        // Default Drupal session cookies use SameSite=Lax
        $response->headers->clearCookie($sessionCookieName, '/', $cookieDomain ?: NULL, TRUE, TRUE, 'Lax');
      }

      // As a safety net, clear any cookie that looks like a Drupal session cookie.
      foreach ($request->cookies->all() as $name => $_) {
        if (preg_match('/^(SSESS|SESS)/', (string) $name)) {
          $response->headers->clearCookie($name, '/', $cookieDomain ?: NULL, TRUE, TRUE, 'Lax');
        }
      }
    }
    catch (\Throwable $e) {
      // Ignore; clearing OAuth cookies above is sufficient to de-auth future /me calls
    }

    return $response;
  }

  /**
   * GET /api/auth/me
   * Return current user info derived from session/token.
   */
  public function me(Request $request): Response {
    $currentUser = $this->currentUser();
    $config = \Drupal::config('system.site');
    $data = null;

    if ($currentUser->isAuthenticated()) {
      $data = [
        'authenticated' => true,
        'uid' => $currentUser->id(),
        'name' => $currentUser->getAccountName(),
        'mail' => $currentUser->getEmail(),
        'roles' => $currentUser->getRoles(),
      ];
    } else {
      // If no Drupal session present, but access_token cookie exists, fetch identity from IdP
      $accessToken = $request->cookies->get('access_token');
      if ($accessToken) {
        $userinfoUrl = (string) ($config->get('oidc_userinfo_url') ?? 'https://openidconnect.googleapis.com/v1/userinfo');
        $ch = curl_init($userinfoUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $rawUserinfo = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
        curl_close($ch);

        if ($status >= 200 && $status < 300 && $rawUserinfo) {
          $claims = json_decode($rawUserinfo, true) ?: [];
          if (!empty($claims)) {
            $email = isset($claims['email']) ? (string) $claims['email'] : NULL;
            $name = isset($claims['name']) ? (string) $claims['name'] : (isset($claims['preferred_username']) ? (string) $claims['preferred_username'] : NULL);
            $sub  = isset($claims['sub']) ? (string) $claims['sub'] : NULL;
            if (!$name) {
              $name = $email ?: ('user_' . substr(sha1((string) $sub), 0, 8));
            }

            // Load or create Drupal user then finalize login so subsequent requests have session
            $storage = \Drupal::entityTypeManager()->getStorage('user');
            $account = NULL;
            if ($email) {
              $candidates = $storage->loadByProperties(['mail' => $email]);
              $account = $candidates ? reset($candidates) : NULL;
            }
            if (!$account) {
              $candidates = $storage->loadByProperties(['name' => $name]);
              $account = $candidates ? reset($candidates) : NULL;
            }
            if (!$account) {
              $account = User::create([
                'name' => $name,
                'mail' => $email ?: ($sub ? $sub . '@example.local' : NULL),
                'status' => 1,
              ]);
              $account->save();
            }
            if ($account) {
              user_login_finalize($account);
              $currentUser = $this->currentUser();
              $data = [
                'authenticated' => true,
                'uid' => $currentUser->id(),
                'name' => $currentUser->getAccountName(),
                'mail' => $currentUser->getEmail(),
                'roles' => $currentUser->getRoles(),
              ];
            }
          }
        }
      }

      // Fallback when no token or IdP request failed
      if (!$data) {
        $data = ['authenticated' => false];
      }
    }

    // Allow dev frontend to call with credentials
    $origin = $request->headers->get('Origin');
    $response = new JsonResponse($data);
    if ($origin) {
      $parsed = parse_url($origin);
      $host = $parsed['host'] ?? '';
      $port = isset($parsed['port']) ? (int) $parsed['port'] : (str_starts_with($origin, 'https://') ? 443 : 80);
      // Allow typical dev variants: localhost, 127.0.0.1, LAN IPs on Vite dev ports (8080, 5173)
      $isLocalDev = ((in_array($port, [8080, 5173], true)) && ($host === 'localhost' || $host === '127.0.0.1' || preg_match('/^(10|172\.(1[6-9]|2\d|3[01])|192\.168)\./', $host)));
      // Also allow same-site frontend on *.lndo.site when session is shared via cookie_domain
      $isLando = (bool) preg_match('/\.lndo\.site$/', $host);
      if ($isLocalDev || $isLando) {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
      }
    }
    return $response;
  }
}


