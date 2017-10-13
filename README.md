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

**Please note that the node templates package only works when using the new React UI.**

## TL;DR

1. `composer require flowpack/nodetemplates`
2. Add templates to your nodetypes configuration in NodeTypes.yaml, as described in the examples below
3. Use the new React UI

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

## Loops and conditions

TBD

## Accessing node data

TBD

## More examples

For more examples have a look at the node templates demo package:

https://github.com/mindscreen/neos-nodetemplates-demo
