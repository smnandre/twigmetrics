<div align="center">

<h1>Twig 🌿 Metrics</h1>

Analyze your Twig templates and get a clear picture of their most important metrics

<img src="./docs/metrics.png" alt="Twig Metrics" width="100%" />

---

&nbsp; ![PHP Version](https://img.shields.io/badge/PHP-8.3+-2e7d32?logoColor=6AB76E&labelColor=010)
&nbsp; ![CI](https://img.shields.io/github/actions/workflow/status/smnandre/twigmetrics/CI.yaml?branch=main&label=Tests&logoColor=white&logoSize=auto&labelColor=010&color=388e3c)
&nbsp; ![Release](https://img.shields.io/github/v/release/smnandre/twigmetrics?label=Stable&logoColor=white&logoSize=auto&labelColor=010&color=43a047)
&nbsp; [![GitHub Sponsors](https://img.shields.io/github/sponsors/smnandre?logo=github-sponsors&logoColor=66bb6a&logoSize=auto&label=%20Sponsor&labelColor=010&color=a5d6a7)](https://github.com/sponsors/smnandre)
&nbsp; ![License](https://img.shields.io/github/license/smnandre/twigmetrics?label=License&logoColor=white&logoSize=auto&labelColor=010&color=2e7d32)

</div>

---

> [!WARNING] 
> TwigMetrics is in active development. Use with caution, and expect things
> to change quickly.

This project is a melting pot of code or ideas I had while working on
various Symfony projects. I'm releasing it as a standalone tool in case
it can be useful to others. 

Depending on the level of interest and feedback, I have plans to expand
it into a more reliable and feature-rich tool. 

## What it does

TwigMetrics scans a directory of `.twig` templates and produces a full report:

- **Template overview**: number of files, directories, lines, characters
- **Code style**: average line length, comment ratio, indentation, formatting
- **Callables**: functions, filters, variables, macros, blocks, tests
- **Architecture**: includes, extends, embeds, imports, inheritance depth
- **Complexity**: logical depth, IF/ELSE/LOOP counts, nesting
- **Maintainability**: large files, high complexity hotspots, risk scores
- **Visual output**: ASCII tables and gauges directly in your terminal

```bash
$ bin/twigmetrics analyze templates/


  ╭─ Template Files ─────╮  ╭─ Logical Comp... ────╮  ╭─ Twig Callables ─────╮
  │  ● ● ● ● ○ ○      C  │  │  ● ● ● ● ● ●      A+ │  │  ● ● ● ● ● ●      A+ │
  ╰──────────────────────╯  ╰──────────────────────╯  ╰──────────────────────╯

  ╭─ Code Style ─────────╮  ╭─ Architecture ───────╮  ╭─ Maintainability ────╮
  │  ● ● ● ● ● ●      A+ │  │  ● ● ● ● ○ ○      C  │  │  ● ● ● ● ● ●      A+ │
  ╰──────────────────────╯  ╰──────────────────────╯  ╰──────────────────────╯
```

See examples of each section in the [Usage](#usage) section below.

## Installation

### Global (soon)

> [!TIP]
> A PHAR release is planned, so you’ll be able to install it globally.

### Composer

```
composer require --dev smnandre/twigmetrics
```

### From source

```
git clone https://github.com/smnandre/twigmetrics.git
cd twigmetrics
composer install
```

## Usage

To analyze a directory of Twig templates, run:

```
vendor/bin/twigmetrics path/to/templates
```

## Twig Metrics

### Template Files

```
  ╭─ Template Files ───────────────────────────────────────────────────────────╮
  │                                                                            │
  │ Templates ................. 188       Directories ................ 19      │
  │ Total Lines ............ 11,204       Avg Lines/Template ....... 59.6      │
  │ Characters ............. 503.8k       Chars/Template .......... 2,680      │
  │ Dir Depth Avg ............. 1.6       File Size CV % .......... 76.3%      │
  │                                                                            │
  ╰────────────────────────────────────────────────────────────────────────────╯
```

### Logical Complexity

```
  ╭─ Logical Complexity ───────────────────────────────────────────────────────╮
  │                                                                            │
  │ Avg Complexity ............ 8.3       Max Complexity ............. 79      │
  │ Avg Depth ................. 1.2       Max Depth ................... 6      │
  │ IFs/Template .............. 1.3       FORs/Template ............. 0.6      │
  │ Nested Control Depth ........ 6                                            │
  │                                                                            │
  ╰────────────────────────────────────────────────────────────────────────────╯
```

### Twig Callables

```
  ╭─ Twig Callables ───────────────────────────────────────────────────────────╮
  │                                                                            │
  │ Funcs/Template ............ 2.9       Filters/Template ......... 18.9      │
  │ Vars/Template ............ 13.5       Unique Funcs ............... 23      │
  │ Unique Filters ............. 31       Macros Defined .............. 5      │
  │                                                                            │
  ╰────────────────────────────────────────────────────────────────────────────╯
```

### Code Style

```
  ╭─ Code Style ───────────────────────────────────────────────────────────────╮
  │                                                                            │
  │ Avg Line Length .......... 41.0       Comments/Template ......... 0.6      │
  │ Comment Ratio ............ 1.0%       Trail Spaces/Line ........ 0.00      │
  │ Empty Lines % ............ 8.2%       Formatting Score ......... 92.7      │
  │ Indent Consistency % ... 100.0%       Naming Conv. Score % .... 97.5%      │
  │                                                                            │
  ╰────────────────────────────────────────────────────────────────────────────╯
```

### Architecture

```
  ╭─ Architecture ─────────────────────────────────────────────────────────────╮
  │                                                                            │
  │ Extends/Template ......... 0.22       Includes/Template ........ 0.57      │
  │ Embeds/Template .......... 0.04       Imports/Template ......... 0.00      │
  │ Avg Inherit Depth ......... 0.2       Standalone Files .......... 122      │
  │                                                                            │
  ╰────────────────────────────────────────────────────────────────────────────╯
```

### Maintainability

```
  ╭─ Maintainability ──────────────────────────────────────────────────────────╮
  │                                                                            │
  │ Large Templates (>200L) ..... 4       High Complexity (>20) ...... 17      │
  │ Deep Nesting (>5) ........... 1       Empty Templates ............. 0      │
  │ Standalone ................ 122       Risk Score .................. A      │
  │                                                                            │
  ╰────────────────────────────────────────────────────────────────────────────╯
```

## Contributing

Feedback, issues, and pull requests are very welcome!
* Issues: [github.com/smnandre/twigmetrics/issues](https://github.com/smnandre/twigmetrics/issues)
* Pull Requests: [github.com/smnandre/twigmetrics/pulls](https://github.com/smnandre/twigmetrics/pulls)

## License

[Twig Metrics](https://github.com/smnandre/twigmetrics) is licensed under the MIT 
License. See the [LICENSE](./LICENSE) file for details.
