# Builder Agent
<center>
  
![License](https://img.shields.io/badge/license-Open%20Source-blue?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8%2B-777BB4?logo=php&style=for-the-badge)
![AI Agent](https://img.shields.io/badge/AI-Agent-lightgrey?style=for-the-badge&logo=robot)
![Status](https://img.shields.io/badge/status-beta-orange?style=for-the-badge)

</center>



**Builder Agent** is a lightweight AI agent written in PHP designed to execute **real system tasks** using structured and extendable functions.

Instead of letting an AI blindly generate code, Builder Agent interacts with the system through **predefined functions** that can manipulate files, create projects, manage directories and automate development workflows.

The architecture is intentionally simple so developers can easily **extend the agent with their own capabilities**.

---

# Philosophy

The core principle behind Builder Agent is simple:

> The AI does not directly execute actions.  
> It calls predefined functions that safely perform operations.

Each function acts as a **controlled capability** the AI can use.

This approach makes the system:

- predictable
- extendable
- secure
- easy to contribute to

---

# Core Features

- Lightweight AI agent written in pure PHP
- Function-based execution system
- Extensible architecture
- File system automation
- Task tracking
- Persistent memory
- Automatic project generation
- ZIP export of generated projects
- Cloudflare Workers AI integration
- Supports multiple programming languages by modifying system prompt
- Extendable with shell command functions for language-specific operations

---

# Project Structure

```
project-root/

- agent.php
- config.php
- index.php
- system.txt
- fnc/
      system functions
- core/routes/
      generated projects
- tmp/
      generated zip archives
- data/
      memory.json
      tasks.json
```

---

# Function System

All capabilities of the agent come from **functions stored inside the `fnc/` directory**.

Each function follows the same structure:

```
fnc/function_name/
    config.json
    function.php
```

This makes it extremely easy for developers to extend the agent.

---

# Example Function Configuration

`config.json`

```json
{
    "name": "check_exists",
    "description": "Check if a file or directory exists",
    "parameters": {
        "path": {
            "type": "string",
            "required": true,
            "description": "Path to verify"
        }
    }
}
```

---

# Example Function Implementation

`function.php`

```php
function execute_check_exists($params) {

    $path = $params['path'] ?? '';

    if (empty($path)) {
        return "Path missing";
    }

    $full_path = __DIR__ . '/../../' . ltrim($path, '/');

    if (file_exists($full_path)) {
        $type = is_dir($full_path) ? 'directory' : 'file';
        return "$type exists: $path";
    } else {
        return "Does not exist: $path";
    }

}
```

---

# Default System Functions

Builder Agent ships with several default functions:

- `check_exists`
- `create_directory`
- `create_file`
- `delete_file`
- `read_file`
- `write_file`
- `zip_directory`

Developers can easily add additional functions to extend the agent capabilities.

---

# Memory System

The agent stores persistent memory inside:

```
data/memory.json
```

This allows the agent to maintain conversation context and system state.

---

# Task System

Every function call is treated as a **task** and recorded in:

```
data/tasks.json
```

This allows the agent to track its actions step by step.

---

# System Prompt

The file:

```
system.txt
```

contains the **core instructions** given to the AI.

**Important:** By modifying the system prompt, the agent can generate projects in **any programming language**, and you can instruct it to:

- create web apps (React, Vue, Laravel, Symfony, etc.)
- create APIs, scripts, or libraries
- follow shell commands for environment setup, dependencies, and installations

This makes Builder Agent highly adaptable and versatile.

---

# Generated Projects

When the agent generates a project, it is stored inside:

```
core/routes/
```

The agent will create a folder and generate the necessary files there.

---

# Temporary Files

ZIP archives created for download are stored inside:

```
tmp/
```

Users receive a direct download link pointing to this directory.

---

# Cloudflare Workers AI Setup

Builder Agent uses **Cloudflare Workers AI models**.

Steps to obtain credentials:

1. Create an account on Cloudflare  
2. Open the Workers AI dashboard  
3. Generate an API token  
4. Retrieve your Account ID

Official documentation:

https://developers.cloudflare.com/workers-ai/

Add your credentials inside:

`config.php`

```php
define("CLOUDFLARE_ACCOUNT_ID", "YOUR_ACCOUNT_ID");
define("CLOUDFLARE_AUTH_TOKEN", "YOUR_API_TOKEN");
```

You can also configure:

- LLM parameters
- request timeout
- max tokens
- output directories
- temporary storage directory

---

# User Interface

The project includes a minimal interface located in:

```
index.php
```

It provides:

- a chat interface
- real-time interaction
- task execution
- project generation

The UI is intentionally simple and can be redesigned freely.

---

# Creating a New Function

To extend the agent:

1. Create a folder inside `fnc/`
2. Add a `config.json`
3. Add a `function.php`
4. Implement your logic
5. Restart the agent

This allows you to add **new system functions**, including shell commands for language-specific operations.

---

# Contributing

Contributions are welcome.

Possible contributions include:

- new system functions
- UI improvements
- performance optimization
- better memory handling
- new automation workflows

---

# Support the Project

If Builder Agent helps you, consider supporting the development.

**Buy me a coffee** ☕
![Buy Me Coffee](https://img.shields.io/badge/☕-Buy%20Me%20a%20Coffee-yellow?style=for-the-badge)
[https://onemarket.mychariow.shop/molly-builderagent](https://onemarket.mychariow.shop/molly-builderagent)

Supporting this project helps maintain it, add new features, and keep it open source for the community.

---

# License

This project is released as **Open Source**.
