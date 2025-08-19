<?php

namespace Drupal\dseza_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

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
    $clientId = getenv('OIDC_CLIENT_ID') ?: '';
    $authorizeUrl = rtrim(getenv('OIDC_AUTHORIZE_URL') ?: '', '/');
    $redirectUri = getenv('OIDC_REDIRECT_URI') ?: '';
    if (!$clientId || !$authorizeUrl || !$redirectUri) {
      return new JsonResponse(['error' => 'OIDC is not configured'], 500);
    }
    // PKCE handled on frontend or via separate endpoint to mint/verifier in session.
    $state = bin2hex(random_bytes(16));
    $codeChallenge = $request->get('code_challenge');
    $codeChallengeMethod = $request->get('code_challenge_method', 'S256');

    $query = http_build_query([
      'response_type' => 'code',
      'client_id' => $clientId,
      'redirect_uri' => $redirectUri,
      'scope' => getenv('OIDC_SCOPE') ?: 'openid profile email',
      'state' => $state,
      'code_challenge' => $codeChallenge,
      'code_challenge_method' => $codeChallengeMethod,
    ]);

    // Store state server-side if desired. Here we set a transient cookie.
    $response = new RedirectResponse($authorizeUrl . '?' . $query, 302);
    $response->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie(
      'oidc_state', $state, time() + 600, '/', NULL, TRUE, TRUE, FALSE, 'Lax'
    ));
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

    $tokenUrl = getenv('OIDC_TOKEN_URL') ?: '';
    $clientId = getenv('OIDC_CLIENT_ID') ?: '';
    $redirectUri = getenv('OIDC_REDIRECT_URI') ?: '';
    if (!$tokenUrl || !$clientId || !$redirectUri) {
      return new JsonResponse(['error' => 'OIDC is not configured'], 500);
    }

    $codeVerifier = $request->get('code_verifier');
    $body = http_build_query([
      'grant_type' => 'authorization_code',
      'code' => $code,
      'redirect_uri' => $redirectUri,
      'client_id' => $clientId,
      'code_verifier' => $codeVerifier,
    ]);

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
    // Set HttpOnly cookies (e.g., access token short-lived + refresh token if present)
    $response = new RedirectResponse(getenv('OIDC_POST_LOGIN_REDIRECT') ?: '/', 302);
    $cookieParams = ['/', NULL, TRUE, TRUE, FALSE, 'Lax'];
    if (!empty($payload['access_token'])) {
      $response->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie('access_token', $payload['access_token'], time() + 900, ...$cookieParams));
    }
    if (!empty($payload['refresh_token'])) {
      $response->headers->setCookie(new \Symfony\Component\HttpFoundation\Cookie('refresh_token', $payload['refresh_token'], time() + 60 * 60 * 24 * 7, ...$cookieParams));
    }
    return $response;
  }

  /**
   * POST /api/auth/logout
   * Clear cookies and optionally call IdP end-session.
   */
  public function logout(Request $request): Response {
    $response = new JsonResponse(['status' => 'ok']);
    $response->headers->clearCookie('access_token', '/', NULL, TRUE, TRUE, 'Lax');
    $response->headers->clearCookie('refresh_token', '/', NULL, TRUE, TRUE, 'Lax');
    return $response;
  }

  /**
   * GET /api/auth/me
   * Return current user info derived from session/token.
   */
  public function me(Request $request): Response {
    $currentUser = $this->currentUser();
    if ($currentUser->isAnonymous()) {
      return new JsonResponse(['authenticated' => false]);
    }
    return new JsonResponse([
      'authenticated' => true,
      'uid' => $currentUser->id(),
      'name' => $currentUser->getAccountName(),
      'mail' => $currentUser->getEmail(),
      'roles' => $currentUser->getRoles(),
    ]);
  }
}


