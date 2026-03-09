# Mattermost Jetlag — GLPI Plugin

A GLPI 11 plugin that sends ticket event notifications to Mattermost channels.

## Features

- **Flexible notification rules** — create rules with filters by ticket type, event, status, category, assignee, and more
- **Webhook & API support** — connect via incoming webhook or Mattermost Bot API
- **Rich message templates** — use macros like `{status}`, `{urgency}`, `{priority}`, `{impact}`, `{type}`, `{subject}`, `{assignee}`, and others in message text
- **Variables Override** — override locale-dependent labels (status names, urgency, priority, etc.) with fixed values of your choice
- **Events Journal** — view a log of all fired notification events with full details
- **Simulation mode** — test your rules without actually sending messages to Mattermost
- **Supports Tickets, Changes, and Problems**

## Requirements

- GLPI 11.0.0 – 11.x
- PHP 8.1+
- Mattermost server with incoming webhook or Bot API access

## Installation

1. Download or clone this repository into your GLPI plugins directory:
   ```
   /glpi/plugins/mattermostjetlag/
   ```
2. In GLPI, go to **Setup → Plugins** and install **Mattermost Jetlag**
3. Go to **Setup → Plugins → Mattermost Jetlag** to configure the connection

## Configuration

### Connection

Choose one of the supported connection types:

- **Webhook** — paste your Mattermost incoming webhook URL. Optionally set a bot nickname and avatar.
- **Bot API** *(coming soon)* — connect using Mattermost API URL and credentials.

### Notification Rules

![Rules List](screenshots/Rules%20List.png)

Each rule defines:
- **Target** — Ticket, Change, or Problem
- **Event** — New, Updated, Solved, Closed, etc.
- **Recipient** — Mattermost channel (e.g. `general`) or user (e.g. `@username`)
- **Message** — free-form text with macros
- **Filters** — optional criteria to limit which items trigger the rule

### Variables Override

By default, GLPI resolves field labels (status, urgency, priority, etc.) using the locale of the user running the hook. The Variables Override tab lets you fix these labels to any language regardless of user locale.

Pre-populated with Russian translations on install.

## Available Macros

| Macro | Description |
|---|---|
| `{subject}` | Ticket/Change/Problem title |
| `{ticket_id}` | Item ID |
| `{status}` | Current status |
| `{type}` | Type (Tickets only: Incident / Request) |
| `{urgency}` | Urgency level |
| `{impact}` | Impact level |
| `{priority}` | Priority level |
| `{category}` | ITIL category name |
| `{requester}` | Requester login(s) |
| `{assignee}` | Assignee login(s) |
| `{observer}` | Observer login(s) |
| `{datetime}` | Event timestamp (ISO 8601) |

## License

[MIT](LICENSE) © 2025 Faithless Padre
