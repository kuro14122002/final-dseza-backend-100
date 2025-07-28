# Architecture

## Maximum Configurability

You have the flexibility to choose which entity types and fields should be included in the schema. By default, no query or mutation fields are provided to expose content.

## Following Drupal Core

The majority of the generated schema aligns with Drupal's entity and field system:

- `Entity` interface for all entities
- `EntityTranslatable` interface for all translatable entities
- `FieldItemList` interface for each field
- `FieldItemType` interface for each field item

## Easy Extensibility

The generated schema serves as a solid foundation for further extension. For instance, a type is generated for the `datetime` field type:

```graphql
type FieldItemTypeDatetime implements FieldItemType {
  isEmpty: Boolean!
  value: String
}
```

Now, schema extensions can effortlessly add additional fields to this type.

```graphql
extend type FieldItemTypeDatetime {
  formatted(format: String): String!
}
```

## Schema Generation Process

The module is fully compatible with the contrib GraphQL module and includes a custom schema called `core_composable` that offers a default schema.

1. The base definitions of all extensions are parsed.
2. The `EntitySchemaBuilder` generates the interfaces and types for selected entity types.
3. The schema is converted to a string.
4. The contents of the `*.extension.graphqls` files are applied to the schema.
5. Finally, the schema is generated.
