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

class   EventStub
extends \Erebot\Event\WithChanSourceTextAbstract
{
    public function __construct(
        \Erebot\Interfaces\Connection   $connection,
                                        $chan,
                                        $source,
                                        $text
    )
    {
        $this->connection  = $connection;
        $this->chan        = $chan;
        $this->source      = $source;
        $this->text        = $text;
    }
}

class   MiniSedTest
extends Erebot_Testenv_Module_TestCase
{
    // Mock TriggerRegistry.
    const MATCH_ANY = '*';

    public function _getMock($text)
    {
        return new EventStub($this->_connection, "#test", "Tester", $text);
    }

    public function setUp()
    {
        $this->_module = new \Erebot\Module\MiniSed('#test');
        parent::setUp();

        // So that the lookup for TriggerRegistry
        // actually returns this instance instead.
        $this->_connection
            ->expects($this->any())
            ->method('getModule')
            ->will($this->returnValue($this));

        $this->_module->reloadModule($this->_connection, 0);
    }

    public function tearDown()
    {
        $this->_module->unloadModule();
        parent::tearDown();
    }

    public function testMiniSed()
    {
        $event = $this->_getMock('Hello foo!');
        $this->_module->handleRawText($this->_eventHandler, $event);
        $this->assertSame(0, count($this->_outputBuffer));

        // Substitute "foo" for "baz".
        $event = $this->_getMock('s/foo/baz/');
        $this->_module->handleSed($this->_eventHandler, $event);
        $this->assertSame(1, count($this->_outputBuffer));
        $this->assertSame(
            "PRIVMSG #test :Hello baz!",
            $this->_outputBuffer[0]
        );

        // Clear the output buffer.
        $this->_outputBuffer = array();
        $event = $this->_getMock('s/z/r/');
        $this->_module->handleSed($this->_eventHandler, $event);
        // Substitute "z" for "r" (baz -> bar).
        // This test proves that you can chain replacements.
        $this->assertSame(1, count($this->_outputBuffer));
        $this->assertSame(
            "PRIVMSG #test :Hello bar!",
            $this->_outputBuffer[0]
        );
    }
}

