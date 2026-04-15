# ZIP rules

When you prepare a manual ZIP for Docs Pro, the archive structure defines the final tree.

## Base rules

- every normal folder creates a `Title`
- every `_` folder creates a `Separator`
- every normal `.md` file creates a `Doc`
- every `_.md` file creates a `Separator`

## Nested folders

Folders can be nested without a practical limit. Every folder creates a `Title` below its parent.

```text
01-Guides/
  01-Backend/
    01-Install.md
```

That produces:

```text
Guides
  Backend
    Install
```

## Numbering

If a name starts with a number, that number is used only for sorting. It is not part of the final display name or slug.

Valid examples:

- `01-Intro`
- `02 Guide`
- `03_Install.md`
- `04-_.md`

## Separators

A separator can be declared in two ways:

- folder `_`
- file `_.md`

You can also number it to control its position:

- `03-_`
- `04-_.md`

If a `_` folder contains files or subfolders, those descendants are ignored. The node still becomes only a separator.

## Without numbering

If you do not use leading numbers, Docs Pro preserves the natural order in which entries are read from the ZIP. It does not invent an extra sort order.

## Assets

Docs Pro now imports non-Markdown assets alongside the tree. Relative image references such as `![Screenshot](../img/example.png)` are preserved on import and exported again with the same relative behavior.
