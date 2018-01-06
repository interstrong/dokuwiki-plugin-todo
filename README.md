# DokuWiki Plugin ToDo


 see https://www.dokuwiki.org/plugin:todo


# addons

todo params:

* at:TEXT — timely label
* pri:TEXT — priority label
* redo:TEXT — repeat (literally, reset due date instead of completion) todo at TEXT days
* label:TEXT — some label

repeat specification:

* redo:N — repeat thru N days
* redo:+N — repeat thru N days
* redo:d:+N — repeat thru N days
* redo:m:+N — repeat thru N monthes
* redo:m:N1,N2 — repeat at N1, N2 and etc days of month
* redo:w:+N — repeat thru N weeks
* redo:w:N1,N2 — repeat at N1, N2 and etc daynames (sun,mon,tue,wed,thu,fri,sat)

TODOLIST params:

* priority:TEXT — show only todos with priority great or equal than TEXT
* labeled:TEXT — show only todos with label TEXT
* setat:! — show only todos without ``at`` param
* setat:* — show only todos with ``at`` param
* DateFormat:FORMAT — show dates with FORMAT

Configuration params:

* DateFormat — set default format for dates

Show details:

* Todos now show as one list, not by pages
* Todos ordered by due, at, pri, todotitle

