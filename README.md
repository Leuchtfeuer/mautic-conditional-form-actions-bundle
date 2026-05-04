# Plugin: Conditional Form Actions Integration by Leuchtfeuer

Conditional form submit actions for Mautic. Execute different follow-up actions based on form or contact field values.

## Overview

- Add conditions to any form action
- Choose from form fields or contact fields using label `Choose form field or contact field`
- Conditions render directly under each action
- Supports AND and OR logic
- Non-conditional actions behave unchanged

## Requirements

- Mautic 5.2
- PHP >= 8.1

## Installation

### Composer
This plugin can be installed through composer.

### Manual Installation
Alternatively, it can be installed manually, following the usual steps:
1. Extract to `plugins/LeuchtfeuerConditionalFormActionsBundle/`
2. Run `php bin/console cache:clear`
3. Run `php bin/console mautic:plugins:reload`
4. Enable plugin in Mautic panel

## Configuration
No configuration required.

## Usage
1. Open a form and go to **Actions**
2. Add an action and click **Add condition**
3. Select a form field or contact field
4. Define the condition and logic
5. Save the form

### Example Scenarios
- Send email if `country = Germany`
- Add to segment if `newsletter_optin = checked`

## Troubleshooting
Make sure you have not only installed but also enabled the Plugin.
If things are still funny, please try `php bin/console cache:clear` and php `bin/console mautic:assets:generate`

## Change log
- https://github.com/Leuchtfeuer/mautic-conditional-form-actions-bundle/releases

## Sponsoring & Commercial Support
We are continuously improving our plugins. If you are requiring priority support or custom features, please contact us at mautic-plugins@leuchtfeuer.com.

## Get Involved
Feel free to open issues or submit pull requests on [GitHub](https://github.com/Leuchtfeuer/mautic-conditional-form-actions-bundle/issues).

## Credits
@patrykgruszka @MadlenF @biozshock

## Author
Leuchtfeuer Digital Marketing GmbH
Please raise any issues in GitHub.
For all other things, please email mautic-plugins@Leuchtfeuer.com

## License
This plugin is licensed under the GPL v3 License.
