# AI & Feedback System - Setup Fix

## Problem
The AI and Feedback system was not working because the required database tables had not been created.

## Solution Implemented
Created the following database tables needed by the AI system:

### 1. `tbl_ai_feedback`
Stores all feedback submissions and AI conversations
- Tracks actor_type (staff, student, parent)
- Categories: 'ai' for AI queries, 'feedback' for user feedback
- Stores AI responses and admin replies
- Status tracking: open, answered, resolved
- Reply tracking with admin/staff metadata

### 2. `tbl_ai_logs`
Audit trail of all AI interactions
- Records every question asked
- Stores AI responses generated
- Tracks role and intent classification
- Created for compliance and learning

### 3. `tbl_ai_queries`
Intent and query analysis database
- Analyzes patterns in user questions
- Tracks detected intents (fees, attendance, performance, etc.)
- Used for analytics and system improvement

### 4. `tbl_ai_recommendations`
Stores AI-generated recommendations for students
- Links recommendations to student IDs
- Tracks category and confidence scores
- Supports personalized coaching features

## AI System Architecture

### Frontend (Widget)
- Location: `script/js/main.js` - Lines 247-450+
- Floating chat widget visible on all authenticated pages
- WhatsApp-style UI with message history
- Two modes: "ai" (Ask Edu) and "feedback" (Send Feedback)
- Auto-resets every 24 hours

### Backend Engine
- Location: `script/core/ai_feedback.php`
- Handles POST requests from widget
- Features:
  - Intent detection (fees, attendance, performance, assignments, reports, etc.)
  - Role-based access control
  - Restricted question validation
  - Data scope filtering per user role
  - Supports both local rules engine and OpenAI integration (optional)

### Admin Interface
- Location: `script/admin/feedback.php`
- View all feedback and AI conversations
- Filter by category (AI/Feedback) and status (Open/Answered/Resolved)
- Reply to feedback with admin message
- Dashboard stats: Total, Open, Resolved, Answered

### Data Collections
Per-user scope includes:
- **Students**: Personal results, attendance, fees, assignments
- **Parents**: Their children's data only
- **Teachers**: Class assignments, submissions, pending marks
- **Admins**: Full school data
- **Accountants**: Finance data only

## Features Now Available

✅ **Greeting Detection**
- "Hi", "Hello", "Good morning" → AI responds warmly

✅ **System Guidance**
- "How do I enter marks?" → Step-by-step instructions
- "How to register a student?" → Guided walkthrough

✅ **Data Queries**
- "Show my results" → Personal performance data
- "What is my attendance?" → Attendance percentage
- "Fee balance?" → Outstanding fees
- "Top student in class?" → (Admins/Teachers only)

✅ **Role-Based Filtering**
- Students only see personal data
- Parents see children's data  
- Teachers see their assigned classes
- Admins see full school data
- Accountants see finance data

✅ **Conversation Memory**
- Stores conversation history per user
- Retrieves last 30 interactions
- Maintains context within session

✅ **Admin Management**
- Review all feedback submissions
- Respond to feedback messages
- Mark as resolved/answered
- Export/print feedback reports

## Environment Variables (Optional)
For advanced AI features with OpenAI:
```
AI_MODE=external          # Enable OpenAI integration
OPENAI_API_KEY=sk-...     # Your API key
OPENAI_MODEL=gpt-4o-mini  # Model to use
```

When not configured, system uses local rule-based responses (which work great).

## Next Steps for Users

1. **Test the Widget**
   - Login to the system
   - Click the chat icon (bottom-right floating button)
   - Try: "Hi", "What is my fee balance?", "How do I enter marks?"

2. **Admin Feedback Management**
   - Go to Admin Panel → AI & Feedback
   - Review user messages
   - Reply with answers

3. **Optional: Enable OpenAI**
   - Get API key from openai.com
   - Set environment variables in `.env` or config
   - System will use AI for more complex reasoning

## Technical Notes

- Tables use Unicode (utf8mb4) for international characters
- Foreign keys referencing tbl_students and tbl_staff for data integrity
- Timestamps auto-set to server time
- Efficient indexing on frequently queried columns
- All queries use prepared statements (SQL injection safe)

## Status: ✅ READY
The AI and Feedback system is now fully operational.
