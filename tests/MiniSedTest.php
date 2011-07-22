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

require_once(
    dirname(__FILE__) .
    DIRECTORY_SEPARATOR . 'testenv' .
    DIRECTORY_SEPARATOR . 'bootstrap.php'
);

class   MiniSedTest
extends ErebotModuleTestCase
{
    // Mock TriggerRegistry.
    const MATCH_ANY = '*';

    public function setUp()
    {
        parent::setUp();

        $this->_connection
            ->expects($this->any())
            ->method('getModule')
            ->will($this->returnValue($this));

        $this->_module = new Erebot_Module_MiniSed('#test');
        $this->_module->reload(
            $this->_connection,
            Erebot_Module_Base::RELOAD_ALL |
            Erebot_Module_Base::RELOAD_INIT
        );
    }

    public function tearDown()
    {
        $this->_module->unload();
        parent::tearDown();
    }

    public function testMiniSed()
    {
        $event = new Erebot_Event_ChanText(
            $this->_connection,
            '#test',
            'Tester',
            'Hello foo!'
        );
        $this->_module->handleRawText($this->_eventHandler, $event);
        $this->assertSame(0, count($this->_outputBuffer));

        // Substitute "foo" for "baz".
        $event = new Erebot_Event_ChanText(
            $this->_connection,
            '#test',
            'Tester',
            's/foo/baz/'
        );
        $this->_module->handleSed($this->_eventHandler, $event);
        $this->assertSame(1, count($this->_outputBuffer));
        $this->assertSame(
            "PRIVMSG #test :Hello baz!",
            $this->_outputBuffer[0]
        );

        // Clear the output buffer.
        $this->_outputBuffer = array();
        $event = new Erebot_Event_ChanText(
            $this->_connection,
            '#test',
            'Tester',
            's/z/r/'
        );
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

