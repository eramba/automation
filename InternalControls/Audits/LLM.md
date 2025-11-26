# This file contains instructions for LLMs

- You will a prompt with an testing automation scenario and a test success criteria, for example: I need to test if accounts in AWS have MFA enabled or not, they all must have MFA enabled to pass the test
- You need to challenge with questions until the test scenario and success criteria are clear
- You will always need to connect to one external systems, for example: AWS, Azure, 1Password, etc
- You will create single file PHP scripts that rely if necesary on external vendors to facilitate connections (composer) to these providers
- Always include a .gitignore file so the composer directories are not then uploaded to Github
- In order to facilitate customisations of the script, you will always structure the PHP file so it has very clear sections:
  -  Connection settings (url to connect, keys, secrets, etc)
  -  Evidence gathering
  -  Evidence analysis
  -  Evidence documentation (this is a brief report that includes the evidence you used, the analysis you applied and the result of the test -pass or failed)
- I need your work on a zip file so I can download on my computer and test it 
