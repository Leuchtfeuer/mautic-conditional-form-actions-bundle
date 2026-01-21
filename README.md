# Plugin: Conditional Form Actions Integration by Leuchtfeuer

Conditional form submit actions for Mautic. Execute different follow-up actions based on form or contact field values.

## Overview

- Add conditions to any form action
- Choose from form fields or contact fields using label `Choose form field or contact field`
- Conditions render directly under each action
- Supports AND and OR logic
- Non-conditional actions behave unchanged

## Requirements

- Mautic 6.0
- PHP >= 8.1

## Installation

1. Extract to `plugins/LeuchtfeuerConditionalFormActionsBundle/`
2. Run `php bin/console cache:clear`
3. Run `php bin/console mautic:plugins:reload`
4. Enable plugin in Mautic panel

## Usage

1. Open a form and go to **Actions**
2. Add an action and click **Add condition**
3. Select a form field or contact field
4. Define the condition and logic
5. Save the form

### Example Scenarios

- Send email if `country = Germany`
- Add to segment if `newsletter_optin = checked`

## Support

Please open issues on GitHub or contact `mautic-plugins@leuchtfeuer.com`.