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

namespace Erebot\Module;

/**
 * \brief
 *      A module that does simple search-and-replace substitutions
 *      using a syntax similar to sed's "s/.../.../" command.
 */
class MiniSed extends \Erebot\Module\Base implements \Erebot\Interfaces\HelpEnabled
{
    /// Handler that is triggered when a substitution is requested.
    protected $handler;
    /// Handler that keeps track of the latest sentence said on each channel.
    protected $rawHandler;
    /// Associative array with the latest sentence on each IRC channel.
    protected $chans;

    /// Regex pattern to detect substitution commands.
    const REPLACE_PATTERN = '@^[sS]([^\\\\a-zA-Z0-9])(.*\\1.*)\\1$@';

    /**
     * This method is called whenever the module is (re)loaded.
     *
     * \param int $flags
     *      A bitwise OR of the Erebot::Module::Base::RELOAD_*
     *      constants. Your method should take proper actions
     *      depending on the value of those flags.
     *
     * \note
     *      See the documentation on individual RELOAD_*
     *      constants for a list of possible values.
     */
    public function reload($flags)
    {
        if (!($flags & self::RELOAD_INIT)) {
            $this->connection->removeEventHandler($this->handler);
            $this->connection->removeEventHandler($this->rawHandler);
        }

        if ($flags & self::RELOAD_HANDLERS) {
            $this->handler = new \Erebot\EventHandler(
                \Erebot\CallableWrapper::wrap(array($this, 'handleSed')),
                new \Erebot\Event\Match\All(
                    new \Erebot\Event\Match\Type('\\Erebot\\Event\\ChanText'),
                    new \Erebot\Event\Match\TextRegex(self::REPLACE_PATTERN)
                )
            );
            $this->connection->addEventHandler($this->handler);

            $this->rawHandler  = new \Erebot\EventHandler(
                \Erebot\CallableWrapper::wrap(array($this, 'handleRawText')),
                new \Erebot\Event\Match\Type('\\Erebot\\Event\\ChanText')
            );
            $this->connection->addEventHandler($this->rawHandler);
        }

        if ($flags & self::RELOAD_MEMBERS) {
            $this->chans = array();
        }
    }

    /**
     * Provides help about this module.
     *
     * \param Erebot::Interfaces::Event::Base::TextMessage $event
     *      Some help request.
     *
     * \param Erebot::Interfaces::TextWrapper $words
     *      Parameters passed with the request. This is the same
     *      as this module's name when help is requested on the
     *      module itself (in opposition with help on a specific
     *      command provided by the module).
     */
    public function getHelp(
        \Erebot\Interfaces\Event\Base\TextMessage   $event,
        \Erebot\Interfaces\TextWrapper              $words
    ) {
        if ($event instanceof \Erebot\Interfaces\Event\Base\PrivateMessage) {
            $target = $event->getSource();
            $chan   = null;
        } else {
            $target = $chan = $event->getChan();
        }

        if (count($words) == 1 && $words[0] === get_called_class()) {
            $msg = $this->getFormatter($chan)->_(
                "This module can be used in a channel to substitude some ".
                "text in the line immediately before using sed's syntax: ".
                "s/regexp/replacement/"
            );
            $this->sendMessage($target, $msg);
            return true;
        }
    }

    /**
     * Performs text substitutions using a regex pattern,
     * with that same syntax as sed's "s/.../.../" command.
     *
     * \param Erebot::Interfaces::EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot::Event::WithChanSourceTextAbstract $event
     *      Substitution command.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleSed(
        \Erebot\Interfaces\EventHandler             $handler,
        \Erebot\Event\WithChanSourceTextAbstract    $event
    ) {
        $chan = $event->getChan();
        if (!isset($this->chans[$chan])) {
            return;
        }

        $previous = $this->chans[$chan];
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
            } else {
                $base = $pos + 2;
            }
        }

        $nbParts   = count($parts);
        if ($nbParts < 2 || $nbParts > 3 || !preg_match('/[a-zA-Z0-9]/', $parts[0])) {
            // Silently ignore invalid patterns
            return;
        }

        $pattern    = '@'.str_replace('@', '\\@', $parts[0]).'@'.
                        (isset($parts[2]) ? $parts[2] : '');
        $subject    = $parts[1];

        $replaced   = preg_replace($pattern, $subject, $previous);
        $this->chans[$chan] = $replaced;
        $this->sendMessage($chan, $replaced);
        return false;
    }

    /**
     * Records the last sentence said in a channel,
     * for every channel the bot has joined.
     *
     * \param Erebot::Interfaces::EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot::Event::WithChanSourceTextAbstract $event
     *      Some sentence that was sent to the channel.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleRawText(
        \Erebot\Interfaces\EventHandler             $handler,
        \Erebot\Event\WithChanSourceTextAbstract    $event
    ) {
        $this->chans[$event->getChan()] = $event->getText();
    }
}
