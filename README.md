# Eramba Automation Scripts

This repository is maintained by **Eramba Limited** (https://www.eramba.org).

It contains **template automation scripts** that help automate tasks within the **Eramba Enterprise** platform.  
These templates are meant to be **adjusted to your specific environment and requirements**.

Feel free to **fork**, modify, and use them as needed. If you want to contribute new scenario templates, please share them at: https://discussions.eramba.org

## Directory Structure

Automation scripts in this repository follow the directory structure: Module / Sub-Module (optional) / Connector_Platform (optional) / Scenario Name

### Examples — Internal Controls / Audits

- `InternalControl/Audits/AWS/Accounts_use_MFA/`
- `InternalControl/Audits/AWS/EC2_has_backup_policy_enforced/`
- `InternalControl/Audits/Azure/EBS_uses_encryption/`
- `InternalControl/Audits/Zoom/Accounts_use_MFA/`

### Example — Risk Module

- `Risk/CustomRiskCalculation/`

## Scripts Structure

All automation scripts are written in **PHP** and include:

1. **Composer Instructions**
2. **A standalone PHP script**

In addition, each script directory includes a `README.md` describing:

- Objective of the script  
- Supported platforms  
- Authentication parameters (if applicable)  
- Configuration parameters (if applicable)
