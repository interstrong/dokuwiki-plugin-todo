<?php

/**
 * ToDo Action Plugin: Inserts button for ToDo plugin into toolbar
 *
 * Original Example: http://www.dokuwiki.org/devel:action_plugins
 * @author     Babbage <babbage@digitalbrink.com>
 * @date 20130405 Leo Eibler <dokuwiki@sprossenwanne.at> \n
 *                replace old sack() method with new jQuery method and use post instead of get \n
 * @date 20130408 Leo Eibler <dokuwiki@sprossenwanne.at> \n
 *                remove getInfo() call because it's done by plugin.info.txt (since dokuwiki 2009-12-25 Lemming)
 */

if(!defined('DOKU_INC')) die();
/**
 * Class action_plugin_todo registers actions
 */
class action_plugin_todo extends DokuWiki_Action_Plugin {

    /**
     * Register the eventhandlers
     */
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('TOOLBAR_DEFINE', 'AFTER', $this, 'insert_button', array());
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, '_ajax_call', array());
    }

    /**
     * Inserts the toolbar button
     */
    public function insert_button(&$event, $param) {
/*
        $event->data[] = array(
            'type' => 'format',
            'title' => $this->getLang('qb_todobutton'),
            'icon' => '../../plugins/todo/todo.png',
// key 't' is already used for going to top of page, bug #76
//      'key' => 't',
            'open' => '<todo>',
            'close' => '</todo>',
            'block' => false,
        );
*/
        $event->data[] = array(
            'type' => 'format',
            'title' => $this->getLang('qb_todobutton_appo'),
            'icon' => '../../plugins/todo/todo.png',
            'open' => '<todo due:2018-18-18 at:12:00>',
            'close' => '</todo>',
            'block' => false,
        );
        $event->data[] = array(
            'type' => 'format',
            'title' => $this->getLang('qb_todobutton_todo'),
            'icon' => '../../plugins/todo/todo.png',
            'open' => '<todo due:2018-18-18>',
            'close' => '</todo>',
            'block' => false,
        );
            $event->data[] = array(
            'type' => 'format',
            'title' => $this->getLang('qb_todobutton_deed'),
            'icon' => '../../plugins/todo/todo.png',
            'open' => '<todo>',
            'close' => '</todo>',
            'block' => false,
        );
        $event->data[] = array(
            'type' => 'format',
            'title' => $this->getLang('qb_todobutton_milestone'),
            'icon' => '../../plugins/todo/todo.png',
            'open' => '<todo label:MS due:2018-18-18>',
            'close' => '</todo>',
            'block' => false,
        );
    }

    /**
     * Handles ajax requests for to do plugin
     *
     * @brief This method is called by ajax if the user clicks on the to-do checkbox or the to-do text.
     * It sets the to-do state to completed or reset it to open.
     *
     * POST Parameters:
     *   index    int the position of the occurrence of the input element (starting with 0 for first element/to-do)
     *   checked    int should the to-do set to completed (1) or to open (0)
     *   path    string id/path/name of the page
     *
     * @date 20140317 Leo Eibler <dokuwiki@sprossenwanne.at> \n
     *                use todo content as change description \n
     * @date 20131008 Gerrit Uitslag <klapinklapin@gmail.com> \n
     *                move ajax.php to action.php, added lock and conflict checks and improved saving
     * @date 20130405 Leo Eibler <dokuwiki@sprossenwanne.at> \n
     *                replace old sack() method with new jQuery method and use post instead of get \n
     * @date 20130407 Leo Eibler <dokuwiki@sprossenwanne.at> \n
     *                add user assignment for todos \n
     * @date 20130408 Christian Marg <marg@rz.tu-clausthal.de> \n
     *                change only the clicked to-do item instead of all items with the same text \n
     *                origVal is not used anymore, we use the index (occurrence) of input element \n
     * @date 20130408 Leo Eibler <dokuwiki@sprossenwanne.at> \n
     *                migrate changes made by Christian Marg to current version of plugin \n
     *
     *
     * @param Doku_Event $event
     * @param mixed $param not defined
     */
    public function _ajax_call(&$event, $param) {
        global $ID, $conf, $lang;

        if($event->data !== 'plugin_todo') {
            return;
        }
        //no other ajax call handlers needed
        $event->stopPropagation();
        $event->preventDefault();

        #Variables
        // by einhirn <marg@rz.tu-clausthal.de> determine checkbox index by using class 'todocheckbox'

        if(isset($_REQUEST['index'], $_REQUEST['checked'], $_REQUEST['pageid'])) {
            // index = position of occurrence of <input> element (starting with 0 for first element)
            $index = (int) $_REQUEST['index'];
            // checked = flag if input is checked means to do is complete (1) or not (0)
            $checked = (boolean) urldecode($_REQUEST['checked']);
            // path = page ID
            $ID = cleanID(urldecode($_REQUEST['pageid']));
        } else {
            return;
        }

        $date = 0;
        if(isset($_REQUEST['date'])) $date = (int) $_REQUEST['date'];

        $INFO = pageinfo();

        #Determine Permissions
        if(auth_quickaclcheck($ID) < AUTH_EDIT) {
            echo "You do not have permission to edit this file.\nAccess was denied.";
            return;
        }
        // Check, if page is locked
        if(checklock($ID)) {
            $locktime = filemtime(wikiLockFN($ID));
            $expire = dformat($locktime + $conf['locktime']);
            $min = round(($conf['locktime'] - (time() - $locktime)) / 60);

            $msg = $this->getLang('lockedpage').'
'.$lang['lockedby'] . ': ' . editorinfo($INFO['locked']) . '
' . $lang['lockexpire'] . ': ' . $expire . ' (' . $min . ' min)';
            $this->printJson(array('message' => $msg));
            return;
        }

        //conflict check
        if($date != 0 && $INFO['meta']['date']['modified'] > $date) {
            $this->printJson(array('message' => $this->getLang('refreshpage')));
            return;
        }

        #Retrieve Page Contents
        $wikitext = rawWiki($ID);

        #Determine position of tag
        if($index >= 0) {
            $index++;
            // index is only set on the current page with the todos
            // the occurances are counted, untill the index-th input is reached which is updated
            $todoTagStartPos = $this->_strnpos($wikitext, '<todo', $index);
            $todoTagEndPos = strpos($wikitext, '>', $todoTagStartPos) + 1;

            if($todoTagEndPos > $todoTagStartPos) {
                // @date 20140714 le add todo text to minorchange
                $todoTextEndPos = strpos( $wikitext, '</todo', $todoTagEndPos );
                $todoText = substr( $wikitext, $todoTagEndPos, $todoTextEndPos-$todoTagEndPos );
                // update text
                $oldTag = substr($wikitext, $todoTagStartPos, ($todoTagEndPos - $todoTagStartPos));
                //$newTag = $this->_buildTodoTag($oldTag, $checked);
                $newTag = $this->_buildTodoTag2($oldTag, $checked);
                $wikitext = substr_replace($wikitext, $newTag, $todoTagStartPos, ($todoTagEndPos - $todoTagStartPos));

                // save Update (Minor)
                lock($ID);
                // @date 20140714 le add todo text to minorchange, use different message for checked or unchecked
                saveWikiText($ID, $wikitext, $this->getLang($checked?'checkboxchange_on':'checkboxchange_off').': '.$todoText, $minoredit = true);
                unlock($ID);

                $return = array(
                    'date' => @filemtime(wikiFN($ID)),
                    'succeed' => true
                );
                $this->printJson($return);
            }
        }
    }

    /**
     * Encode and print an arbitrary variable into JSON format
     *
     * @param mixed $return
     */
    private function printJson($return) {
        $json = new JSON();
        echo $json->encode($return);
    }

    /**
     * @brief gets current to-do tag and returns a new one depending on checked
     * @param $todoTag    string current to-do tag e.g. <todo @user>
     * @param $checked    int check flag (todo completed=1, todo uncompleted=0)
     * @return string new to-do completed or uncompleted tag e.g. <todo @user #>
     */
    private function _buildTodoTag($todoTag, $checked) {
        $user = '';
        if($checked == 1) {
            if(!empty($_SERVER['REMOTE_USER'])) { $user = $_SERVER['REMOTE_USER']; }
            $newTag = preg_replace('/>/', ' #'.$user.':'.date('Y-m-d').'>', $todoTag);
        } else {
            $newTag = preg_replace('/[\s]*[#].*>/', '>', $todoTag);
        }
        return $newTag;
    }

    /**
     * @brief gets current to-do tag and returns a new one depending on checked and redo
     * @param $todoTag    string current to-do tag e.g. <todo @user>
     * @param $checked    int check flag (todo completed=1, todo uncompleted=0)
     * @return string new to-do completed or uncompleted tag e.g. <todo @user #> with redo support
     */
    private function _buildTodoTag2($todoTag, $checked) {
        $user = '';
        $m = array();
        $redo = false;
        $due = false;
        if($checked == 1) {
            if(!empty($_SERVER['REMOTE_USER'])) { $user = $_SERVER['REMOTE_USER']; }
            if(preg_match('/redo:([0-9,a-zA-Z\+:]+)/',$todoTag,$m)) {
                $redo = $m[1];
                if(preg_match('/due:([0-9\-]+)/',$todoTag,$m)) {
                    $due = date_modify(date_create($m[1]),$this->_parseRedo($redo,$m[1]));
                } else {
                    $due = date_create(date('Y-m-d'));
                }
                $newTag = preg_replace('/due:[0-9\-]+/', 'due:'.date_format($due,'Y-m-d'), $todoTag);
            } else {
                $newTag = preg_replace('/>/', ' #'.$user.':'.date('Y-m-d').'>', $todoTag);
            }
        } else {
            $newTag = preg_replace('/[\s]*[#].*>/', '>', $todoTag);
        }
        return $newTag;
    }

    private function _parseRedo($redoText,$dueText) {
        $due = strtotime($dueText); if (!$due) return '';
        if (strpos($redoText,'m:+')===0) {
            return '+'.substr($redoText,3).' month';
        }
        else if (strpos($redoText,'m:')===0) {
            $a = explode(',',substr($redoText,2));
            if (sizeof($a)==0) return '';
            sort($a,SORT_NUMERIC);
            $t = (int)date('j',$due);
            foreach($a as $i) if ((int)$i>$t) return $i.' '.date('M',$due);
            return $a[0].' '.date('M',strtotime('next month',$due));
        }
        else if (strpos($redoText,'w:+')===0) {
            return '+'.substr($redoText,3).' week';
        }
        else if (strpos($redoText,'w:')===0) {
            $b = explode(',',substr($redoText,2));
            if (sizeof($b)==0) return '';
            $a = array();
            foreach($b as $i) {
                if ($i=='mo' or $i=='mon') $a[] = 1;
                if ($i=='tu' or $i=='tue') $a[] = 2;
                if ($i=='we' or $i=='wed') $a[] = 3;
                if ($i=='th' or $i=='thu') $a[] = 4;
                if ($i=='fr' or $i=='fri') $a[] = 5;
                if ($i=='sa' or $i=='sat') $a[] = 6;
                if ($i=='su' or $i=='sun') $a[] = 0;
                if ($i==7) $a[] = 0;
                else $a[] = (int)$i;
            };
            sort($a,SORT_NUMERIC);
            $s = 'sunmontuewedthufrisat';
            $t = (int)date('w',$due);
            foreach($a as $i) if ($i>$t) return 'next '.substr($s,$i*3,3);
            return 'next '.substr($s,$a[0]*3,3);
        }
        else if (strpos($redoText,'d:+')===0) {
            return '+'.substr($redoText,3).' day';
        }
        else if (strpos($redoText,'+')===0) {
            return '+'.substr($redoText,1).' day';
        }
        else return '+'.$redoText.' day';
    }
    /**
     * Find position of $occurance-th $needle in haystack
     */
    private function _strnpos($haystack, $needle, $occurance, $pos = 0) {
        for($i = 1; $i <= $occurance; $i++) {
            $pos = strpos($haystack, $needle, $pos) + 1;
        }
        return $pos - 1;
    }
}
