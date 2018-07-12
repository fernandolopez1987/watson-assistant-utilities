entity-renamer.php
==================

Rename your entities across all dialog nodes with ease.

Designed for use with IBM’s [Watson Assistant](https://www.ibm.com/watson/ai-assistant/). 


Source
------

- [entity-renamer.php](entity-renamer.php), 20 KB


Demo
----

[https://neatnik.net/watson/assistant/utilities/entity-renamer.php](https://neatnik.net/watson/assistant/utilities/entity-renamer.php)


Usage
-----

This script runs in a web browser over HTTP. Change the name of an entity in the list, press enter, and then click the button to confirm. The entity will be renamed everywhere across all dialog conditions.


### Example

<img alt="Sample view of the Entity Renamer script" src="https://github.com/neatnik/watson-assistant-tools/raw/master/entity-renamer-example.png" width="500" height="326">


Configuration
-------------

Open `entity-renamer.php`, locate the Configuration section, and set the following values:

- **WORKSPACE** (Usually 36 characters and looks like `1c7fbde9-102e-4164-b127-d3ffe2e58a04`)
- **USERNAME** (Usually 36 characters and looks like `febeea03-84c4-57cb-af25-5f44b7af1f05`)
- **PASSWORD** (Usually 12 characters and looks like `xCkZnpPbxLkQ`)
- **VERSION** (The ISO 8601 date of the API version, currently `2018-02-16` so you probably won’t need to change this)

You can find the Workspace ID, Username, and Password values in your Watson Assistant workspace. Click the Deploy tab, then the Credentials screen, and it should all be displayed there.


Legal
-----

Copyright (c) 2018 Neatnik LLC. This software is distributed under the terms of the [MIT License](LICENSE).

IBM Watson® is a registered trademark of IBM Corporation.