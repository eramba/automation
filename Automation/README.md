## Testing Simple Automations

This is a list of very simple automations that help you understand how to work with **Macros**, **Tools**, and **Secrets**.

To test these automations:

1. Go to **Control Catalogue / Internal Controls**.
2. Click **“Add”**.
3. Complete the mandatory fields in the form with any data, as this is just a test item. On the **“Audit”** tab, click **“Audits Required”** and select **“Manual”**. Select at least one date so that at least one audit record is created, and complete the other fields, such as methodology, with any data.
4. Save and make sure the audit record is created under **Control Catalogue / Internal Controls / Audits**.

The following script can be created on the **Audit** module. It will:

- Read the ID of an audit you created.
- Edit the audit record and complete it using the wrapper edit function.
- Create an additional audit record and complete it.
- Write a comment to both audit records.
- Write a binary attachment to both audit records.
- Get the current temperature of Marbella and write it to disk using Composer.
- Read the file, extract the temperature, and print it to `STDOUT`.