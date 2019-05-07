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

## Conditions and loops

### Conditions

If you want to apply a template only under some conditions, you can use the ``when`` configuration
key. It can be used in the main node template or in any child node template.

The following example hides a newly created node if the title entered in the node creation dialog
contains the string "dummy":

```yaml
'Neos.NodeTypes:Page':
  options:
    template:
      properties:
        _hidden: true
      when: '${String.indexOf(String.toLowerCase(data.title), "dummy") >= 0}'
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

There are several variables available in the EEL context that allow for accessing node data, for example.

| Variable name  | Description                                                                               | Availability            |
|----------------|-------------------------------------------------------------------------------------------|-------------------------|
| data           | Data from the node creation dialog                                                        | Global (if data exists) |
| triggeringNode | The main node whose creation triggered template processing                                | Global                  |
| node           | The current node that has been created (equals triggeringNode for the outermost template) | Global                  |
| parentNode     | The parentNode of the current node                                                        | Child nodes             |
| item           | The current item inside a withItems loop                                                  | Inside withItems loop   |
| key            | The current key inside a withItems loop                                                   | Inside withItems loop   |

## Node creation depth

The node creation depth can be configured via Settings.yaml with `nodeCreationDepth`, defaults to `10`. 

```yaml
Flowpack:
  NodeTemplates:
    nodeCreationDepth: 10
``` 

## More examples

For more examples have a look at the node templates demo package:

https://github.com/mindscreen/neos-nodetemplates-demo
