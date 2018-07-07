dialog-debugger.php
===================

Use this tool to debug your dialog.

Designed for use with IBM’s [Watson Assistant](https://www.ibm.com/watson/ai-assistant/) (formerly the Watson Conversation Service). 


Source
------

- [dialog-debugger.php](dialog-debugger.php), 16 KB


Demo
----

[https://neatnik.net/watson/assistant/utilities/dialog-debugger.php?query=tell%20me%20a%20joke](https://neatnik.net/watson/assistant/utilities/dialog-debugger.php?query=tell%20me%20a%20joke)


Usage
-----

This script runs in a web browser over HTTP. Use the input field at the top to send a message to your workspace, and then view the information about the response.

### Example

<img alt="Example output from the dialog-debugger.php script output in a web browser" src="https://github.com/neatnik/watson-assistant-tools/raw/master/dialog-debugger-example.png" width="500" height="432">


Configuration
-------------

Open `dialog-debugger.php`, locate the Configuration section, and set the following values:

- **WORKSPACE** (Usually 36 characters and looks like `1c7fbde9-102e-4164-b127-d3ffe2e58a04`)
- **USERNAME** (Usually 36 characters and looks like `febeea03-84c4-57cb-af25-5f44b7af1f05`)
- **PASSWORD** (Usually 12 characters and looks like `xCkZnpPbxLkQ`)
- **VERSION** (The ISO 8601 date of the API version, currently `2018-02-16` so you probably won’t need to change this)

You can find the Workspace ID, Username, and Password values in your Watson Assistant workspace. Click the Deploy tab, then the Credentials screen, and it should all be displayed there.


Legal
-----

Copyright (c) 2018 Neatnik LLC. This software is distributed under the terms of the [MIT License](LICENSE).

IBM Watson® is a registered trademark of IBM Corporation.