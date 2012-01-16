Usage
=====

Provided commands
-----------------

This module provides the following commands:

..  table:: Commands provided by |project|

    +-----------+-----------------------------------------------------------+
    | Command   | Description                                               |
    +===========+===========================================================+
    | |cmd1| or | Search and replace *pattern* with *replacement string* in |
    | |cmd2|,   | the last sentence written in the current channel. This is |
    | etc.      | a very basic implementation of sed's ``s///`` command.    |
    |           | Flags cannot be used with this implementation.            |
    +-----------+-----------------------------------------------------------+

Example
-------

..  sourcecode:: irc

    20:37:25 <+Foobar> this is kewl
    20:37:27 <+Foobar> s/kewl/so cool/
    20:37:27 < Erebot> this is so cool

..  |cmd1| replace:: :samp:`s/{pattern}/{replacement string}/`
..  |cmd2| replace:: :samp:`s@{pattern}@{replacement string}@`

.. vim: ts=4 et
