MediaWiki extension to generate an SVG radar chart from data using `<radar>` tag.

## Usage

```
<radar size="300" max="1">
0.2 Foo
0.5 Bar
1 Baz
</radar>
```

Data is line-delimited. First word is length of the line on this axis,
remaining text is label. Lengths are scaled based on `max` value.
