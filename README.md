# Eramba Automation Scripts

This repository is maintained by **Eramba Limited** (https://www.eramba.org). This repository is made of template automation scripts that must be adjusted to your needs. Feel free to fork, adapt and use it on your environment. If you would like to contribute with a new scenario template please post your code at https://discussions.eramba.org

It contains **sample automation scripts** that help automate tasks within the **Eramba Enterprise** platform.

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

You must adapt these scripts template approach to your specific needs!
