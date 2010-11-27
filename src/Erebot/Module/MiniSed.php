<?php
/*
    This file is part of Erebot.

    Erebot is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Erebot is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Erebot.  If not, see <http://www.gnu.org/licenses/>.
*/

class   Erebot_Module_MiniSed
extends Erebot_Module_Base
{
    static protected $_metadata = array(
        'requires'  =>  array(
            'Erebot_Module_TriggerRegistry',
        ),
    );
    protected $_handler;
    protected $_rawHandler;
    protected $_chans;

    const REPLACE_PATTERN = '@^[sS]([^\\\\a-zA-Z0-9])(.*\\1.*)\\1$@';

    public function reload($flags)
    {
        if (!($flags & self::RELOAD_INIT)) {
            $registry   =&  $this->_connection->getModule(
                'Erebot_Module_TriggerRegistry'
            );
            $matchAny   =   Erebot_Utils::getVStatic($registry, 'MATCH_ANY');

            $this->_connection->removeEventHandler($this->_handler);
            $this->_connection->removeEventHandler($this->_rawHandler);
        }

        if ($flags & self::RELOAD_HANDLERS) {
            $registry   =&  $this->_connection->getModule(
                'Erebot_Module_TriggerRegistry'
            );
            $matchAny   =   Erebot_Utils::getVStatic($registry, 'MATCH_ANY');

            $filter         = new Erebot_TextFilter($this->_mainCfg);
            $filter->addPattern(
                Erebot_TextFilter::TYPE_REGEXP,
                self::REPLACE_PATTERN,
                FALSE
            );
            $this->_handler = new Erebot_EventHandler(
                                    array($this, 'handleSed'),
                                    'Erebot_Event_ChanText',
                                    NULL, $filter);
            $this->_connection->addEventHandler($this->_handler);

            $this->_rawHandler  = new Erebot_EventHandler(
                                        array($this, 'handleRawText'),
                                        'Erebot_Event_ChanText',
                                        NULL);
            $this->_connection->addEventHandler($this->_rawHandler);
        }

        if ($flags & self::RELOAD_MEMBERS)
            $this->_chans = array();
    }

    public function handleSed(Erebot_Event_WithChanSourceTextAbstract &$event)
    {
        $chan = $event->getChan();
        if (!isset($this->_chans[$chan]))
            return;

        $previous = $this->_chans[$chan];
        preg_match(self::REPLACE_PATTERN, $event->getText(), $matches);

        $parts  = array();
        $base   = 0;
        $char   = $matches[1];
        $text   = $matches[2];
        while ($text != '') {
            $pos = $base + strcspn($text, '\\'.$char, $base);
            if ($pos >= strlen($text) || $text[$pos] == $char) {
                $parts[]    = substr($text, 0, $pos);
                $text       = substr($text, $pos + 1);
                $base       = 0;
            }

            else
                $base = $pos + 2;
        }

        $nb_parts   = count($parts);
        if ($nb_parts < 2 || $nb_parts > 3)
            return; // Silently ignore invalid patterns

        if (!preg_match('/[a-zA-Z0-9]/', $parts[0]))
            return;

        $pattern    = '@'.str_replace('@', '\\@', $parts[0]).'@'.
                        (isset($parts[2]) ? $parts[2] : '');
        $subject    = $parts[1];

        $replaced   = preg_replace($pattern, $subject, $previous);
        $this->_chans[$chan] = $replaced;
        $this->sendMessage($chan, $replaced);

        return FALSE;
    }

    public function handleRawText(
        Erebot_Event_WithChanSourceTextAbstract &$event
    )
    {
        $this->_chans[$event->getChan()] = $event->getText();
    }
}

?>
