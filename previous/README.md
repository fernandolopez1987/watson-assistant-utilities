Watson Assistant Utilities
==========================

I’m working on a suite of simple tools that work with IBM’s [Watson Assistant](https://www.ibm.com/watson/ai-assistant/) (formerly the Watson Conversation Service):

- [entity-renamer](entity-renamer-readme.md): renames entities wherever they’re used, across all dialog conditions
- [dialog-debugger](dialog-debugger-readme.md): displays specific entity, intent, and dialog node information for inputs to your workspace, which can help in debugging unexpected dialog
- [disconnected-entities](disconnected-entities-readme.md): finds dialog nodes that use non-existent entities, and lists entities that aren't used in dialog conditions
- log-csv-exporter: exports a workspace's conversation log in CSV format
- log-csv-exporter-standalone: same as log-csv-exporter but with a nice web interface that allows for date selection and configuration


Legal
-----

Copyright (c) 2019 Neatnik LLC. This software is distributed under the terms of the [MIT License](LICENSE).

IBM Watson® is a registered trademark of IBM Corporation.