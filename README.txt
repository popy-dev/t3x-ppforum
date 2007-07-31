
This is only a Alpha version, so many things will change (many function will be removed/moved, some hooks will be added/renamed), so reading the code is the best documentation for now.

The little list of parts which may not change
  - Forums, Topics and messages displaying functions and hooks
  - 'getCounters' functions
  - CSS selector
  - query/object caching
  - UPDATE events and their hooks

Parser objects :
  They are declared in plugin.tx_ppforum_pi1.parsers
      plugin.tx_ppforum_pi1.parsers.parserkey {
        objectRef = <object reference, t3lib_div::getUserObj compatible>

        what.you.want = as you want : all these propreties will me gived to your object throught the method init
      }
  Required methods :
    init($conf, &$forumInstance) : Object initialisation
      * $conf is the typoscript config array (plugin.tx_ppforum_pi1.parsers.parserkey)
      * $forumInstance is a back reference to the plugin object

    getTitle($hsc) : Return the parse title
      * $hsc is a boolean used to determine if the string have to be passed throught htmlspecialchars (but prefer using tx_pplib_div::htmlspecialchars)
    
    printToolbar() : print the user toolbar

    parse($text) : Parse a text
      * $text is the text to parse

Golden Rules to build a new css template :
  Each css selector must begin (or contain) .tx_ppforum_pi1
  font-size are allowed in "em" or "%", but not "px" values