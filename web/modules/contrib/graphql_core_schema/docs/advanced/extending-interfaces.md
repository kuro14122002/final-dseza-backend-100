# Extending Interfaces

When extending the schema using the `*.extension.graphqls` files, it's important to note that adding a field to an interface does not automatically add it to all types that implement the interface. For example, if you want to add a field to the `Node` interface and have it available in all types implementing it (e.g., `NodePage`, `NodeArticle`, etc.), you need to extend each type individually.

Extending the `Node` interface alone without extending the implementing types will result in an invalid schema. To resolve this, you can extend the interface and add the field to each implementing type separately. Here's an example:

```graphql
extend interface Node {
  myCustomField: String
}

extend type NodePage {
  myCustomField: String
}

extend type NodeArticle {
  myCustomField: String
}

extend type NodeTopic {
  myCustomField: String
}
```

While this approach works for a small number of types, it becomes cumbersome and hard to maintain as the number of types grows. To address this issue, there are two recommended solutions:

## Using a computed field

Defining a custom computed field on the entity type is a clean and effective solution. This approach allows the field to be available both in GraphQL and outside of it. You can refer to the [Dynamic/Virtual field values using computed field property classes](https://www.drupal.org/docs/drupal-apis/entity-api/dynamicvirtual-field-values-using-computed-field-property-classes) tutorial for guidance on creating a computed field. After creating the computed field, you need to enable it in the schema configuration. Detailed instructions for enabling fields can be found in the [configuration documentation](/basics/configuration.html#enabling-fields).

## Defining the interface in an EXTENSION.base.graphqls file

The schema builder makes sure to not generate an interface or type twice. This means that if you define an interface in your `EXTENSION.base.graphqls` file, it will be used as the base and extended by the schema builder. This also works when the interface/type is defined multiple times in multiple extension.

Note that this behavior is not according to the specs and will result in an invalid schema if parsed by any other module/library.

### Example

The media extension declares an interface for `Media`, defining only a single field:

<<< @/../graphql/media.base.graphqls{graphql}

The schema builder will parse the definition and then extend the interface with fields enabled for this entity type. When the types for the entity bundles are generated, they will automatically inherit all fields.

This "magic inheritance" also works when adding an interface to an interface. Here's an example:

```graphql
interface EntityPaywalled {
  isPaywalled: boolean
}

interface Media implements EntityPaywalled
interface Node implements EntityPaywalled
```

This will automagically implement the `EntityPaywalled` interface and inherit all the fields defined by it.
