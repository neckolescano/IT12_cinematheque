# System Evaluation Questionnaires
## Cinematheque Centre Davao Ticketing and Reservation System

---

### Respondent Information

| | |
|---|---|
| Name (optional) | ______________________________ |
| Evaluator group | ☐ Staff (AVT / PDO)  ☐ Moviegoer / Client representative |
| Date of Evaluation | ______________________________ |

### Instructions

Answer this questionnaire **after** the system demonstration and your hands-on use of the system. Answer based on the part of the system you used: the moviegoer site, the staff (admin) site, or both.

### Scenario

**Moviegoer:** A moviegoer wants to reserve seats for a screening by browsing or searching the screenings, choosing preferred seats on the seat map, entering the booker's and attendees' details, paying through PayMongo (paid screenings only), and receiving an e-ticket by email.

**Staff:** A staff member wants to manage a screening by logging in, creating or editing the screening, approving pending free reservations, checking payment status, admitting arriving attendees at the door, and viewing the attendance report.

### User Goal

To successfully reserve seats for a screening and receive an e-ticket (moviegoer), and to manage screenings, reservations, and admission accurately (staff).

---

## 1. Usability Evaluation

This part assesses the ease of use, convenience, and overall user experience of the system using Nielsen's 10 usability heuristics. For each heuristic, rate how severe any usability problem you found is, and give the reason for your rating.

**Severity Rating Scale (Nielsen)**

| Rating | Description |
|:-:|---|
| 0 | I don't agree that this is a usability problem at all |
| 1 | Cosmetic problem only: need not be fixed unless extra time is available on project |
| 2 | Minor usability problem: fixing this should be given low priority |
| 3 | Major usability problem: important to fix, so should be given high priority |
| 4 | Usability catastrophe: imperative to fix this before product can be released |

### 1. Visibility of System Status
- The system shall clearly display the current reservation step (Seats, Details, Payment, Confirmed).
- The system shall show the number of seats left for each screening and which seats are already taken.
- The system shall update the booking summary (selected seats and total amount) as seats are selected.
- The system shall show a loading indicator while a form or payment request is being processed.
- The system shall clearly display the reservation status (Pending, Approved, Cancelled) and the admission status of each attendee.

Rating: _____
Reason: ______________________________________________________________

### 2. Match Between System and the Real World
- The system shall use familiar cinema terms such as "Choose seats," "Booking summary," "Admit," and "No-show."
- The seat map shall reflect the actual seating layout with row and seat labels (e.g., A1–J10).
- Dates, times, and amounts shall be shown in a familiar format (e.g., ₱ for prices).
- Instructions and messages shall be written in simple and understandable language.

Rating: _____
Reason: ______________________________________________________________

### 3. User Control and Freedom
- The system shall allow users to go back and change their seat selection before submitting the reservation.
- The system shall allow users to return from PayMongo checkout without paying and try the payment again later.
- The system shall allow staff to undo an admission recorded by mistake.
- The system shall ask for confirmation before staff cancel a reservation or delete a record.

Rating: _____
Reason: ______________________________________________________________

### 4. Consistency and Standards
- Buttons, fonts, colors, and layout positions shall remain consistent across all pages.
- Status labels and colors (Pending, Approved, Cancelled) shall be the same on all pages and in the emails.
- Standard symbols (e.g., arrow for back, "×" for close) shall be used.

Rating: _____
Reason: ______________________________________________________________

### 5. Error Prevention
- The system shall prevent selecting seats that are already reserved, so no seat can be double-booked.
- The system shall limit a booking to a maximum of 10 seats.
- The system shall require all needed details (including the booker's email) before a reservation can be submitted.
- The system shall prevent staff from admitting attendees whose reservations are not yet approved.

Rating: _____
Reason: ______________________________________________________________

### 6. Recognition Rather Than Recall
- The seat map shall show available, selected, and taken seats with a legend, so users do not need to remember them.
- The booking summary shall keep the chosen screening, seats, and total visible while filling in details.
- The booking reference shall be shown on the booking page and in the email, so users do not need to memorize it.
- Staff shall be able to filter the attendee checklist (All, To admit, Admitted, Pending, Cancelled) instead of remembering who has arrived.

Rating: _____
Reason: ______________________________________________________________

### 7. Flexibility and Efficiency of Use
- The system shall allow users to search screenings by title and filter by free or paid.
- The system shall allow staff to search bookings across all screenings by reference, name, email, or phone number.
- The system shall allow staff to approve all pending free reservations of a screening at once.
- The system shall allow staff to admit attendees quickly without reloading the page.

Rating: _____
Reason: ______________________________________________________________

### 8. Aesthetic and Minimalist Design
- Each page shall show only the information needed for the current task.
- The layout shall have a clear visual hierarchy, with the main action easy to find.
- The interface shall use readable fonts and sufficient contrast, in both light and dark mode.

Rating: _____
Reason: ______________________________________________________________

### 9. Help Users Recognize, Diagnose, and Recover from Errors
- The system shall display clear error messages beside the field that needs correction.
- The system shall explain when a payment was cancelled or not yet completed, and offer a way to pay again or check the payment status.
- The system shall inform the user when a booking reference cannot be found.

Rating: _____
Reason: ______________________________________________________________

### 10. Help and Documentation
- The system shall provide short hints on forms where needed (e.g., where the confirmation email is sent, the format of the booking reference).
- The system shall tell the user what happens next (e.g., payment through PayMongo, or waiting for staff approval for free screenings).
- The e-ticket shall include admission instructions for the screening.

Rating: _____
Reason: ______________________________________________________________

**User Feedback:**

______________________________________________________________________________

______________________________________________________________________________

---

## 2. Functional Requirements Evaluation

This part determines whether the system performs the functions specified in the requirements: whether each function works as expected, produces the expected output, performs the intended process, handles valid and invalid inputs appropriately, and meets the stated requirement.

**Rating scale**

| 1 | 2 | 3 | 4 | 5 |
|:-:|:-:|:-:|:-:|:-:|
| Strongly Disagree | Disagree | Neutral | Agree | Strongly Agree |

### Part A. Moviegoer Functions

| Functional Requirement | Evaluation Question | Rating |
|---|---|:-:|
| Screening Listing | Does the system display the list of screenings and their details without requiring a login? | 1 2 3 4 5 |
| Search and Filter | Does the search and free/paid filter provide relevant screening results? | 1 2 3 4 5 |
| Seat Availability | Does the system show the correct number of available seats for each screening? | 1 2 3 4 5 |
| Seat Selection | Does the system allow selecting one or more available seats (up to 10) while preventing seats already reserved from being selected? | 1 2 3 4 5 |
| Reservation Submission | Does the system allow submitting a reservation with the booker's details and one attendee per seat, without creating an account? | 1 2 3 4 5 |
| Input Validation | Does the system reject incomplete or invalid entries (e.g., missing email, invalid contact details) and show clear messages? | 1 2 3 4 5 |
| Booking Reference | Does the system generate a unique booking reference for each reservation? | 1 2 3 4 5 |
| Online Payment | For paid screenings, does the system redirect to PayMongo checkout and mark the reservation as Approved only after payment is confirmed? | 1 2 3 4 5 |
| Booking Lookup | Does the system retrieve the correct booking when the booking reference is entered? | 1 2 3 4 5 |
| E-Ticket and Email Notifications | Does the system send the reservation, approval (e-ticket), and cancellation emails to the booker's email address? | 1 2 3 4 5 |

### Part B. Staff Functions

| Functional Requirement | Evaluation Question | Rating |
|---|---|:-:|
| Staff Login | Does the system allow authorized staff to log in successfully and refuse deactivated or invalid accounts? | 1 2 3 4 5 |
| Screening Management | Does the system allow staff to add, view, update, and delete screenings as required? | 1 2 3 4 5 |
| Reservation Management | Does the system allow staff to search, view, and cancel reservations as required? | 1 2 3 4 5 |
| Free Booking Approval | Does the system allow staff to approve pending free reservations, individually or all at once? | 1 2 3 4 5 |
| Payment Status | Does the system correctly show the PayMongo payment status of each paid reservation? | 1 2 3 4 5 |
| Admission (Attendance Checklist) | Does the system allow staff to admit only approved attendees, undo an admission, and add notes? | 1 2 3 4 5 |
| No-Show Identification | Does the system correctly identify approved seats with no recorded attendance as no-shows? | 1 2 3 4 5 |
| Report Generation | Does the system generate the reservation and attendance report correctly and export it as CSV? | 1 2 3 4 5 |
| Film Catalog and Seat Management | Does the system allow staff to add, view, update, and delete films, actors, directors, genres, and seats as required? | 1 2 3 4 5 |
| Staff Account Management | Does the system allow staff to create, update, and deactivate staff accounts, with AVT and PDO having the same access? | 1 2 3 4 5 |

---

## 3. Non-Functional Requirements Evaluation

This part evaluates the quality attributes of the system. Use the same 1–5 rating scale as Part 2.

| Non-Functional Requirement | Evaluation Question | Rating |
|---|---|:-:|
| Performance | Does the system respond within an acceptable time when loading pages and saving reservations? | 1 2 3 4 5 |
| Reliability | Does the system operate consistently during use, without errors or double-booked seats? | 1 2 3 4 5 |
| Security | Does the system provide appropriate protection for staff accounts and reservation data? | 1 2 3 4 5 |
| Compatibility | Does the system work properly in standard web browsers on computers and mobile phones? | 1 2 3 4 5 |
| Maintainability | Can system updates or maintenance (e.g., managing screenings, films, seats, and staff accounts) be performed appropriately? | 1 2 3 4 5 |
| Accessibility | Can the intended users access and use the system appropriately (readable text, clear contrast, usable on small screens)? | 1 2 3 4 5 |

---

### Comments and Suggestions

______________________________________________________________________________

______________________________________________________________________________

---

## Scoring Notes (for the proponents, not for respondents)

**Usability (Part 1):** compute the mean severity rating per heuristic and per evaluator group. Lower is better: a mean close to 0 means evaluators found no real usability problem. Use the written reasons to identify what needs fixing, starting with the heuristics rated 3 or 4.

The chapter guide asks the results to discuss eight usability aspects. These heuristics cover them as follows:

| Aspect (chapter guide) | Heuristics |
|---|---|
| Ease of learning | 2, 6, 10 |
| Ease of navigation | 1, 3 |
| Understandability of functions | 2, 10 |
| Ease of completing tasks | 5, 7 |
| Interface clarity | 1, 8 |
| Consistency of the interface | 4 |
| User satisfaction | User Feedback and overall ratings |
| Overall ease of use | Mean across all 10 heuristics |

**Parts 2 and 3:** compute the mean rating per item and per evaluator group.

## References

Nielsen, J. (1994). *10 usability heuristics for user interface design*. Nielsen Norman Group. https://www.nngroup.com/articles/ten-usability-heuristics/

Nielsen, J. (1994). *Severity ratings for usability problems*. Nielsen Norman Group. https://www.nngroup.com/articles/how-to-rate-the-severity-of-usability-problems/
