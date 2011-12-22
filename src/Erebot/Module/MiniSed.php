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

/**
 * \brief
 *      A module that does simple search-and-replace substitutions
 *      using a syntax similar to sed's "s/.../.../" command.
 */
class   Erebot_Module_MiniSed
extends Erebot_Module_Base
{
    /// Handler that is triggered when a substitution is requested.
    protected $_handler;
    /// Handler that keeps track of the latest sentence said on each channel.
    protected $_rawHandler;
    /// Associative array with the latest sentence on each IRC channel.
    protected $_chans;

    /// Regex pattern to detect substitution commands.
    const REPLACE_PATTERN = '@^[sS]([^\\\\a-zA-Z0-9])(.*\\1.*)\\1$@';

    /**
     * This method is called whenever the module is (re)loaded.
     *
     * \param int $flags
     *      A bitwise OR of the Erebot_Module_Base::RELOAD_*
     *      constants. Your method should take proper actions
     *      depending on the value of those flags.
     *
     * \note
     *      See the documentation on individual RELOAD_*
     *      constants for a list of possible values.
     */
    public function _reload($flags)
    {
        if (!($flags & self::RELOAD_INIT)) {
            $registry   = $this->_connection->getModule(
                'Erebot_Module_TriggerRegistry'
            );
            $matchAny   = Erebot_Utils::getVStatic($registry, 'MATCH_ANY');

            $this->_connection->removeEventHandler($this->_handler);
            $this->_connection->removeEventHandler($this->_rawHandler);
        }

        if ($flags & self::RELOAD_HANDLERS) {
            $registry   = $this->_connection->getModule(
                'Erebot_Module_TriggerRegistry'
            );
            $matchAny   = Erebot_Utils::getVStatic($registry, 'MATCH_ANY');

            $this->_handler = new Erebot_EventHandler(
                new Erebot_Callable(array($this, 'handleSed')),
                new Erebot_Event_Match_All(
                    new Erebot_Event_Match_InstanceOf('Erebot_Event_ChanText'),
                    new Erebot_Event_Match_TextRegex(self::REPLACE_PATTERN)
                )
            );
            $this->_connection->addEventHandler($this->_handler);

            $this->_rawHandler  = new Erebot_EventHandler(
                new Erebot_Callable(array($this, 'handleRawText')),
                new Erebot_Event_Match_InstanceOf('Erebot_Event_ChanText')
            );
            $this->_connection->addEventHandler($this->_rawHandler);
        }

        if ($flags & self::RELOAD_MEMBERS)
            $this->_chans = array();
    }

    /// \copydoc Erebot_Module_Base::_unload()
    protected function _unload()
    {
    }

    /**
     * Performs text substitutions using a regex pattern,
     * with that same syntax as sed's "s/.../.../" command.
     *
     * \param Erebot_Interface_EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot_Event_WithChanSourceTextAbstract $event
     *      Substitution command.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleSed(
        Erebot_Interface_EventHandler           $handler,
        Erebot_Event_WithChanSourceTextAbstract $event
    )
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

        $nbParts   = count($parts);
        if ($nbParts < 2 || $nbParts > 3 ||
            !preg_match('/[a-zA-Z0-9]/', $parts[0]))
            return; // Silently ignore invalid patterns

        $pattern    = '@'.str_replace('@', '\\@', $parts[0]).'@'.
                        (isset($parts[2]) ? $parts[2] : '');
        $subject    = $parts[1];

        $replaced   = preg_replace($pattern, $subject, $previous);
        $this->_chans[$chan] = $replaced;
        $this->sendMessage($chan, $replaced);
        return FALSE;
    }

    /**
     * Records the last sentence said in a channel,
     * for every channel the bot has joined.
     *
     * \param Erebot_Interface_EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot_Event_WithChanSourceTextAbstract $event
     *      Some sentence that was sent to the channel.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleRawText(
        Erebot_Interface_EventHandler           $handler,
        Erebot_Event_WithChanSourceTextAbstract $event
    )
    {
        $this->_chans[$event->getChan()] = $event->getText();
    }
}

