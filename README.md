# Neos Node Templates

When using Neos CMS as an editor, you often work with nested node structures that 
have to be created manually. This packages aims at easing the editing workflow by
automatically creating helpful child nodes and making useful modifications to node 
properties when creating new nodes in the Neos UI.

In contrast to child nodes that are defined in the regular node type definition
(they cannot be removed by the editor), all modifications that are made when a 
template is applied can be changed or removed by the editor.

The desired node structure is defined in a declarative way in the NodeTypes.yaml
under the path "options.template".

## TL;DR

1. `composer require flowpack/nodetemplates`
2. Add templates to your nodetypes configuration in NodeTypes.yaml, as described in the examples below
3. Or use the command to dump the template based on your workspace

## Hello world

The following example will add a text child node with the content "Hello World"
to the main content collection of all pages that are created via the UI:

```yaml
'Neos.NodeTypes:Page':
  options:
    template:
      childNodes:
        mainContentCollection:
          name: 'main'
          childNodes:
            helloWorldTextNode:
              type: 'Neos.NodeTypes:Text'
              properties:
                text: '<p>Hello world!</p>'
```

## Using the node creation dialog

The Neos React UI comes with a configurable node creation dialog. You can access
the data entered in the node creation dialog in your node templates using EEL queries.
You could let the editor choose between different dummy texts like this:

```yaml
'Neos.NodeTypes:Page':
  ui:
    creationDialog:
      elements:
        dummyText:
          type: string
          ui:
            label: 'Dummy text'
            editor: 'Neos.Neos/Inspector/Editors/SelectBoxEditor'
            editorOptions:
              values:
                'Hello world':
                  label: 'Hello world'
                'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua.':
                  label: 'Lorem ipsum'
  options:
    template:
      childNodes:
        mainContentCollection:
          name: 'main'
          childNodes:
            helloWorldTextNode:
              type: 'Neos.NodeTypes:Text'
              properties:
                text: '${"<p>" + data.dummyText + "</p>"}'
```

## Conditions and loops

### Conditions

If you want to apply a template only under some conditions, you can use the ``when`` configuration
key. It can be used in the main node template or in any child node template.

The following example only creates a text node if the option was selected in the creation dialog:

```yaml
'Your.NodeType:ContentCollection':
  ui:
    creationDialog:
      elements:
        createText:
          type: boolean
  options:
    template:
      childNodes:
        text:
          type: 'Your.NodeType:Text'
          properties:
            text: "bar"
          when: '${data.createText}'
```

As a ``when`` condition that evaluates to ``false`` prevents the whole template (and all child
templates) from being applied, its most common use case is conditional child node creation.

### Loops

Loops can be used to create multiple child nodes. You can use ``withItems`` to define the items
of the loop. When using EEL, be sure to return an array. The current item is available in EEL 
expressions as the ``item`` context variable.

The following example creates three different text child nodes in the main content collection:

```yaml
'Neos.NodeTypes:Page':
  options:
    template:
      childNodes:
        mainContentCollection:
          name: 'main'
          childNodes:
            multipleTextNodes:
              type: 'Neos.NodeTypes:Text'
              properties:
                text: '${"<p>" + item + "</p>"}'
              withItems:
                - 'Hello world'
                - 'Different text'
                - 'Yet another text'
```

We call conditions ``when`` and loops ``withItems`` (instead of ``if`` and ``forEach``),
because it inspires a more declarative mood. The naming is inspired by Ansible.

## EEL context variables

There are several variables available in the EEL context for example.

| Variable name  | Type                 | Description                                                | Availability          |
|----------------|----------------------|------------------------------------------------------------|-----------------------|
| data           | array<string, mixed> | Data from the node creation dialog                         | Global                |
| triggeringNode | NodeInterface        | The main node whose creation triggered template processing | Global                |
| item           | mixed                | The current item inside a withItems loop                   | Inside withItems loop |
| key            | string               | The current key inside a withItems loop                    | Inside withItems loop |

### Additional context

You can add more context variables to a template via the ``withContext`` setting. ``withContext``
takes an arbitrary array of items whose values might also contain EEL expressions:

```
template:
  withContext:
    someText: '<p>foo</p>'
    processedData: "${String.trim(data.bla)}"
    booleanType: true
    arrayType: ["value"]
  childNodes:
    column0Tethered:
      name: column0
      childNodes:
        content0:
          type: 'Flowpack.NodeTemplates:Content.Text'
          when: "${booleanType}"
          withItems: "${arrayType}"
          properties:
            text: ${someText + processedData + item}
```

Inside ``withContext`` the parent context may be accessed in EEL expressions, but sibling context
values are not available. As ``withContext`` is evaluated before ``when`` and ``withItems``, you can
access context variables from ``withContext`` in ``withItems`` at the same level – but not the other
way around.

## Fine-grained error handling, resuming with the next possible operation.

In the first step the configuration is processed, exceptions like those caused by an EEL Expression are caught, and any malformed parts of the template are ignored (with their errors being logged).
This might lead to a partially processed template with some properties or childNodes missing.

You can decide via the error handling configuration `Flowpack.NodeTemplates.errorHandling`, if you want to start the node creation of this partially processed template (`stopOnException: false`) or abort the process (`stopOnException: true`), which will only lead to creating the root node, ignoring the whole template.

```yaml
Flowpack:
  NodeTemplates:
    errorHandling:
     templateConfigurationProcessing:
        stopOnException: false
```

In case exceptions are thrown in the node creation of the template, because a node constraint was not met or the `type` field was not set, the creation of the childNode is aborted, but we continue with the node creation of the other left over parts of the template.
It behaves similar with properties: In case a property value doesn't match its declared type the exception is logged, but we will try to continue with the next property.

## Create template from node subtree

When creating a more complex node template (to create multiple pages and content elements) it can be helpful to take the current node subtree from your workspace as reference.
For this case you can use the command:

```sh
flow nodeTemplate:createFromNodeSubtree <nodeIdentifier>
```

It will give you the output similar to the example above.
References to Nodes and non-primitive property values are commented out in the YAML.

## More examples

For more examples have a look at the node templates demo package:

https://github.com/mindscreen/neos-nodetemplates-demo
