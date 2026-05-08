# SRMS Full User Manual

## 1. Overview
This School Records Management System (SRMS) supports the main daily work of a CBC school from learner admission to exams, report cards, merit lists, fees, attendance, discipline, and parent/student access.

The system has separate portals for:
- `Admin / Headteacher / Super Admin`
- `Academic Office / Deputy Headteacher`
- `Teacher`
- `Student`
- `Parent`
- `Accountant`
- `BOM`

The system is permission-based. What each user sees depends on role level, assigned permissions, and visible module allocation.

## 2. Main Roles And What They Do
### Admin / Headteacher
- Manage system setup
- Create classes and streams
- Register and manage students
- Register and manage staff
- Assign teachers to classes and subjects
- Create exams and approve marks
- Lock/unlock results
- Generate report cards, class summaries, and merit lists
- Manage finance, communication, and governance modules

### Academic Office / Deputy Headteacher
- Manage academic records
- Create classes, combinations, terms, and subjects
- Support exams, results, and reports
- Review learner progress

### Teacher
- Take attendance
- Enter CBC assessments and exam marks
- View subject/class results
- View grading system
- Access student and discipline information where permitted

### Accountant
- Manage fees, payments, invoices, and finance views

### Student
- View report cards
- View attendance, fees, discipline, learning modules, timetable, and profile

### Parent
- View child report card
- View attendance, fees, discipline, and learning access

## 3. Core System Flow
The normal school workflow is:

1. Set up school structure
2. Register staff and students
3. Create classes, subjects, and teacher allocations
4. Create terms and exams
5. Enter attendance and marks
6. Review and approve marks
7. Lock results
8. Generate report cards
9. Generate class summary and merit list
10. Publish or share with students/parents

## 4. First-Time Setup
### 4.1 Staff Setup
From the admin portal:
- Add users for headteacher, deputy, academic office, teachers, accountant, BOM, and support staff
- Assign role level and permissions
- Confirm email and login details

### 4.2 Class Setup
From `Class Management`:
- Create each class
- Add stream name if used in your naming pattern
- Assign class teacher
- Assign grading system for the class

Important:
- Each class can have its own grading system
- The class grading system now flows into report card point calculation and merit calculations

### 4.3 Subject Setup
From `Subject Catalog`:
- Create subjects
- Define which subjects are offered
- Set subject combinations where needed
- Allocate teachers to subjects/classes

### 4.4 Term And Session Setup
From `Terms & Sessions`:
- Create academic year terms
- Confirm active term order

## 5. Student Management
From `Register Students`, `Manage Students`, and `Import Students`:
- Register student ID and school ID
- Capture names, gender, email, class, and profile details
- Bulk import learners where needed

The student record feeds:
- attendance
- results
- report cards
- discipline
- fees
- parent linking

## 6. Teacher Allocation
From `Subject Teachers` / `Teacher Allocation`:
- Assign subject teachers to class subject structures
- Confirm teacher-class-subject mapping before exams begin

This is important because teacher portals use these allocations to:
- show correct mark entry screens
- control which teacher can enter which subject marks

## 7. Attendance
### Teacher/Admin Attendance Flow
1. Open attendance session
2. Select class
3. Mark present/absent
4. Save session

Attendance affects:
- learner analytics
- report card attendance display
- parent/student visibility

## 8. Exams And Assessments
### 8.1 Exam Creation
From `Exams`:
- create exam
- assign class
- assign term
- assign exam type
- select assessment mode if applicable

### 8.2 Exam Status Lifecycle
Typical exam lifecycle:
- `draft`
- `active/open`
- `reviewed`
- `published`

Marks should normally be entered while the exam is active/open, then reviewed and published later.

### 8.3 Mark Entry
Teachers use:
- exam marks entry
- CBC entry where applicable

The system saves:
- raw subject score or CBC level source
- grade label
- grade points
- exam linkage where supported

### 8.4 Mark Review
Admin/academic office can:
- approve marks
- reject marks
- reopen where needed

## 9. Grading System
The school can use:
- marks-based grading
- CBC grading
- class-specific grading systems

Each class can be assigned a grading system, and that assignment is used by:
- report cards
- merit list point totals
- mean point calculation
- subject point display

Current report behavior:
- visible report `Score` is now the point score from the assigned grading system
- totals and means on report cards are point-based

## 10. Results Locking
Before final reports:
1. confirm marks are complete
2. approve or review any rejected entries
3. lock results

Locking is recommended before:
- report card generation
- merit list generation
- PDF printing

## 11. Report Cards
### 11.1 Generate Report Cards
From `Report Tool`:
1. select class
2. select term
3. select exam
4. click `Generate Report Cards`

The system:
- computes each learner result
- stores report cards
- computes ranking data
- prepares report PDFs and verification codes

### 11.2 Report Card Contents
The current report card includes:
- student details
- subject `Score` using class grading points
- subject grade
- total score
- mean score
- remarks
- QR / verification code

### 11.3 Dev And Trend
Current behavior:
- `Dev` is point-based difference between learner subject score and class mean point score
- subject `Trend` compares current point score to previous term point score
- overall card `Trend` compares current overall mean to previous term overall mean

## 12. Performance Summary
From `Report Tool > Performance Summary`:
- select class
- select term
- select exam
- generate class summary PDF

This is used for school review, not individual learner publishing.

## 13. Merit List
### 13.1 Merit Generation
Merit can now be generated from:
- `Report Tool > Merit List`
- `Open Merit List` page

You can select:
- class
- term
- exam

### 13.2 Merit Contents
The current merit list includes:
- position
- school/admission ID
- student name
- gender
- subject-by-subject score columns
- total points
- mean points
- grade
- trend
- remark
- verification code

### 13.3 Merit PDF
The merit PDF:
- uses landscape layout
- auto-fits many subjects inside the page
- has signature lines for:
  - class teacher
  - deputy headteacher
  - headteacher

### 13.4 Merit Screen View
The merit page now has:
- horizontal scrolling
- sticky identity columns
- exam-aware filtering

## 14. Bulk Results And Bulk Downloads
From `Report Tool`:
- open bulk results
- print class result sheets
- open bulk report card downloads

Use these for:
- class-wide printing
- archived result packs
- admin review

## 15. Finance
Finance modules support:
- fee structure
- invoices
- payment capture
- receipts
- finance summaries
- accountant workflows

Finance data can appear in learner-facing portals and report card sections where configured.

## 16. Discipline
Discipline modules support:
- case recording
- case updates
- notices and hearings
- learner discipline visibility

This can support report and welfare tracking, though the merit list currently focuses on academic ranking.

## 17. Parent Portal
Parents can use their portal to:
- view child report card
- view fees
- view attendance
- view discipline updates
- access learning-facing modules where enabled

## 18. Student Portal
Students can:
- view report cards
- view attendance
- view fees
- view timetable
- access e-learning
- view discipline and welfare information where enabled

## 19. Common Admin Tasks
### To Open A New Term
1. create term
2. confirm class setup
3. confirm teacher allocations
4. create exams
5. begin attendance and marks entry

### To Correct Wrong Report Output
1. check class grading system
2. confirm marks saved correctly
3. check exam selection
4. regenerate report cards
5. regenerate merit list if rankings changed

### To Add A New Class
1. create class
2. assign grading system
3. attach subjects
4. assign class teacher
5. allocate subject teachers

## 20. Troubleshooting
### Merit list blank or empty
Check:
- class selected
- term selected
- exam selected if filtering by exam
- marks exist for that class/term
- results are computed/generated

### Report card totals look wrong
Check:
- class grading system assignment
- whether the report is using point-based score
- whether old cached report cards need regeneration

### Teachers cannot see mark entry
Check:
- subject allocation
- class assignment
- exam status
- teacher permissions

### PDF overflows
The merit PDF now auto-compacts for many subjects, but if subject names are unusually long, use shorter subject labels in the subject catalog for cleaner printouts.

## 21. Recommended Best Practice
- finish class and subject setup before term opens
- always assign grading systems at class level
- review teacher allocations before creating exams
- lock results before issuing final reports
- regenerate report cards after grading/ranking changes
- verify one sample learner before printing all PDFs

## 22. Current Practical Limits
- stream ranking is not yet fully separated because the current class table does not store a dedicated stream field
- merit ranking is currently strongest at class level
- exam-aware merit is available, but depends on properly published exam data

## 23. Quick Reference
### Where To Generate What
- Report cards: `Admin > Report Tool > Generate Report Cards`
- Class summary: `Admin > Report Tool > Performance Summary`
- Merit list: `Admin > Report Tool > Merit List` or `Admin > Merit List`
- Bulk results: `Admin > Report Tool > Bulk Results`
- Bulk report downloads: `Admin > Report Tool > Bulk Report Card Downloads`

### Most Important Rule
If output looks wrong, first verify:
- class
- term
- exam
- grading system
- whether results were regenerated after changes
