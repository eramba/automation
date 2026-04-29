# Build an Online Assessment Questionnaire from a Compliance Package

## Change log

| Version | Date | Author | Change summary |
|---|---|---|---|
| v1.0 | 2026-04-28 09:22:20 Europe/Madrid | Esteban Ribičić | Initial structured prompt for generating an Online Assessment questionnaire from a Compliance Package. |
| v1.1 | 2026-04-28 09:22:20 Europe/Madrid | Esteban Ribičić | Added rule that question titles must use only the Compliance Requirement title and must not append generic wording. |
| v1.2 | 2026-04-28 09:22:20 Europe/Madrid | Esteban Ribičić | Added rule that question descriptions must contain only practical examples, without generic explanatory filler text. |
| v1.3 | 2026-04-28 09:22:20 Europe/Madrid | Esteban Ribičić | Added requirement to include author, date with seconds, and version in every future prompt correction. |

---

## Required input files

This task requires **two CSV files**:

1. **Online Assessment Questionnaire Import Template CSV**
   - Defines the exact columns, formatting, and import structure required.
   - The generated output CSV must follow this template exactly.

2. **Compliance Package CSV with Instructions on what policy and controls must include**
   - Contains the compliance requirements that must be converted into an Online Assessment questionnaire.
   - You need to pay special attention to the column "Mandatory Policy Statements (separated by | character)" and "Recommended Internal Controls (separated by | character)" as these provide the guidance you need to know what questions to make for each ISO requirement

Do not proceed unless both files are available.

---

## Objective

Create a completed **Online Assessment questionnaire import CSV** that can be sent to customers to assess their compliance gap against the attached Compliance Package.

The questionnaire must be practical, clear, and understandable by people without a GRC background.

---

## Objective Details

- You must produce the Online Assessment (OA) CSV in a way that matches the chapter and questions provided in the Compliance Package
- The first question of every chapter must be a dropdown (Yes/No) asking if the chapter is applicable to the person answering the OA, if it is applicable you show all questions inside the chapter otherwise you keep them hidden
- The OA question id and title must relate to the requirement id and title in the compliance pacakge, keep the title the same unless is longer than 400 characters, then make a summary to keep it short
- The OA question description depends on the columns "Mandatory Policy Statements" and "Recommended Internal Controls". 
 
 - If the "Recommended Internal Controls" column is empty the OA Question Description should be formatted as: Do you have one or more documents that cover the folllwing topics? "Mandatory Policy Statements" (IMPORTANT, those statements are too raw, make them nicer gramatically). The possible answers are "Yes" (in which case you show a warning asking them to attach the documents), "No", and "I'm not sure"

 - If the "Recommended Internal Controls" column is NOT empty the OA Question Description should be formatted as: Is anyone in your organisation doing the following tasks? "Recommended Internal Controls" (IMPORTANT, those statements are too raw, make them nicer gramatically). The possible answers are "Yes", "No", and "I'm not sure".
  - If the answer is "Yes" then show a "OpenAnswer" question (otherwise hidden) asking them to "Provide the name of team who does these tasks (if more than one team, write for each task who does it) and what technology they use to perform that task. If they have any documented procedure they need to import it as attachment"

---

## Versioning and prompt correction rules

Every time this prompt is corrected or improved, update the **Change log** at the top of the file.

Each change log entry must include:

- Version number
- Date and time with seconds
- Author name
- Short change summary

Use this format:

```text
vX.X | YYYY-MM-DD HH:MM:SS Timezone | Esteban Ribičić | Summary of the change.