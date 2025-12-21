## System: AWS (EC2 / EBS) — Option B (IAM User + Access Keys)

### Required Variables
- `AWS_ACCESS_KEY_ID`: Access key for the IAM user you will create.
- `AWS_SECRET_ACCESS_KEY`: Secret key for the IAM user.
- `AWS_SESSION_TOKEN`: Leave unset (only used for temporary credentials).
- `AWS_REGIONS`: Comma-separated list of AWS regions to scan (example: `eu-west-1,eu-central-1`).
- `EBS_LABEL_SOURCE`: Which volume “label” to match. Default: `tag:Name`.
- `EBS_VOLUME_NAME_REGEX`: Regex used to filter volumes by label. Default: `/.*/` (matches everything).

### Steps to create accounts and roles

#### Step 1 — Create the IAM user
1. Open the AWS Console.
2. Go to **IAM → Users → Create user**.
3. User name: `eramba-automation-ebs-audit` (or equivalent).
4. **Do not** enable AWS Management Console access.
5. Proceed without adding permissions for now.

#### Step 2 — Create an access key
1. Open the newly created user.
2. Go to **Security credentials**.
3. Under **Access keys**, click **Create access key**.
4. Select **Application running outside AWS / CLI**.
5. Save the **Access key ID** and **Secret access key** securely (the secret will not be shown again).

#### Step 3 — Attach least-privilege permissions
1. Go to **Permissions → Add permissions → Create inline policy**.
2. Choose **JSON** and paste the policy below.
3. Name the policy something like `EbsEncryptionAuditReadOnly`.

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "EbsEncryptionAuditReadOnly",
      "Effect": "Allow",
      "Action": [
        "ec2:DescribeVolumes",
        "ec2:DescribeTags"
      ],
      "Resource": "*"
    }
  ]
}