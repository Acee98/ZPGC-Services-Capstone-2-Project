# Software size (ZPGC Services V1.6)

**Counted:** 19 October 2026  
**Folder:** application files under the project root  
**Product label:** ZPGC Services V1.6

Nothing was removed from the running desk. Layout rules that used to sit inside `pages/partials/critical_ui_fixes.php` (about 4,800 lines of CSS in a PHP file) now load from `css/critical_ui_fixes.css`. Admin, technician, user, and ticket pages still include the same partial; it only prints a stylesheet link.

## What to put in the paper

| Set | Files | Physical lines | Non-blank lines |
|-----|------:|---------------:|----------------:|
| Application PHP (`pages/` + `logic/`, not probes) | 97 | 15,898 | 15,046 |
| Client JS we wrote (`js/`, not Chart.js) | 12 | 2,956 | 2,794 |
| Styles (`css/`, including `critical_ui_fixes.css`) | 9 | 14,004 | 12,804 |

**Suggested sentence:** The helpdesk application is about **15,000** non-blank lines of PHP, about **2,800** lines of project JavaScript, and about **12,800** lines of CSS. Chart.js is a library and is not counted. Probe scripts under `ai/` and `tools/` (about 2,200 PHP lines) are not the product.

## Do not count as “our application”

- `js/chart.umd.js` — Chart.js  
- `ai/` and `tools/` — smoke tests, probes, load helper  
- Uploads, logs, screenshots, Word/PDF paper files  

## Totals if someone walks the whole tree

A raw count of every `.php` / `.js` / `.css` file in the folder is larger because it adds probes, tools, and the Chart.js file. Use the table above for Chapter 4 / software specifications.
