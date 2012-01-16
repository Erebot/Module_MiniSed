Configuration
=============

Options
-------

This module does not provide any configuration options.


Example
-------

In this example, we simply make this module available on all networks
and channels the bot is connected to.

..  parsed-code:: xml

    <?xml version="1.0"?>
    <configuration
      xmlns="http://localhost/Erebot/"
      version="0.20"
      language="fr-FR"
      timezone="Europe/Paris">

      <modules>
        <!-- Other modules ignored for clarity. -->

        <module name="|project|"/>
      </modules>
    </configuration>

..  vim: ts=4 et
