---
paths:
  - 'app/Http/Requests/**'
---

# Requests

## Test the payload the form actually sends, not a hand-built one
Inertia's `useForm` posts every field it holds on every submit, including ones left blank. A rule set that only guards the required case will still judge that blank value: `Rule::requiredIf(false)` does not skip the other rules, so a bare `Rule::in([...])` rejected every ordinary save while eight tests passed by omitting the field entirely.

Add `'nullable'` alongside a conditional `requiredIf`, and write at least one test that posts the field blank the way the form does. A test that builds the request by hand is testing an API you imagined.
