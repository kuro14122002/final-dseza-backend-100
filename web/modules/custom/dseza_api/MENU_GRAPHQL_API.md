# Menu GraphQL API Documentation

## Overview
DSEZA API sử dụng GraphQL Compose để cung cấp menu functionality với hỗ trợ song ngữ đầy đủ. Module dseza_api_menu đã được tích hợp và xóa bỏ để tránh conflict.

## GraphQL Endpoints

### Main Endpoint
```
https://dseza-backend.lndo.site/graphql/dseza_api
```

### Language-Specific Endpoints
```
https://dseza-backend.lndo.site/en/graphql/dseza_api  (English)
https://dseza-backend.lndo.site/vi/graphql/dseza_api  (Vietnamese)
```

## Menu Queries

### 1. Basic Menu Query
Lấy menu cơ bản với tất cả items:

```graphql
query GetMainMenu {
  menuByName(name: "main") {
    id
    name
    langcode
    items {
      id
      title
      url
      langcode
      weight
      expanded
      enabled
      children {
        id
        title
        url
        langcode
        weight
        expanded
        enabled
      }
    }
  }
}
```

### 2. Multi-language Menu Query  
Lấy menu theo ngôn ngữ cụ thể:

```graphql
query GetMenuByLanguage($menuName: String!, $langcode: String!) {
  menuByName(name: $menuName, langcode: $langcode) {
    id
    name
    langcode
    items {
      id
      title
      url
      langcode
      weight
      expanded
      enabled
      children {
        id
        title
        url
        langcode
        weight
        expanded
        enabled
        children {
          id
          title
          url
          langcode
          weight
        }
      }
    }
  }
}
```

Variables:
```json
{
  "menuName": "main",
  "langcode": "vi"
}
```

### 3. Nested Menu Query (Deep)
Lấy menu với độ sâu tùy chỉnh:

```graphql
query GetDeepMenu {
  menuByName(name: "main") {
    items {
      ...MenuItemFragment
      children {
        ...MenuItemFragment
        children {
          ...MenuItemFragment
          children {
            ...MenuItemFragment
          }
        }
      }
    }
  }
}

fragment MenuItemFragment on MenuItem {
  id
  title
  url
  langcode
  weight
  expanded
  enabled
  description
}
```

### 4. Specific Menu Query
Lấy menu cụ thể (ví dụ: footer menu):

```graphql
query GetFooterMenu {
  footerMenu: menuByName(name: "footer") {
    items {
      id
      title
      url
      langcode
      children {
        id
        title
        url
        langcode
      }
    }
  }
}
```

## Frontend Integration Examples

### React/TypeScript Example
```typescript
const MENU_QUERY = gql`
  query GetMainMenu($langcode: String!) {
    menuByName(name: "main", langcode: $langcode) {
      items {
        id
        title
        url
        langcode
        children {
          id
          title
          url
          langcode
        }
      }
    }
  }
`;

// Usage
const { data, loading, error } = useQuery(MENU_QUERY, {
  variables: { langcode: 'vi' }
});
```

### JavaScript/Fetch Example
```javascript
const getMenu = async (langcode = 'en') => {
  const query = `
    query GetMainMenu($langcode: String!) {
      menuByName(name: "main", langcode: $langcode) {
        items {
          id
          title
          url
          langcode
          children {
            id
            title
            url
            langcode
          }
        }
      }
    }
  `;

  const response = await fetch('https://dseza-backend.lndo.site/graphql/dseza_api', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      query,
      variables: { langcode }
    })
  });

  return response.json();
};
```

## Common Menu Names
- `main` - Main navigation menu
- `footer` - Footer menu
- `user-menu` - User account menu
- `tools` - Tools/utilities menu

## Language Codes
- `en` - English
- `vi` - Vietnamese (Tiếng Việt)

## Benefits of Integration
1. **No Conflicts**: Removed dseza_api_menu to eliminate GraphQL Compose conflicts
2. **Unified API**: All DSEZA functionality in one module
3. **Better Performance**: Uses GraphQL Compose optimizations
4. **Language Support**: Built-in multilingual menu support
5. **Consistent**: Same authentication and CORS handling as other APIs

## Migration Notes
- dseza_api_menu module has been removed
- All menu functionality now uses GraphQL Compose
- Custom menu resolvers are no longer needed
- Existing menu queries should work without changes 