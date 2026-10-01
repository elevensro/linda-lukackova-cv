# Linda Lukackova — static CV website

A responsive HTML/CSS adaptation of the approved CV. Oughter headings, Poppins body text, a circular pencil-sketch portrait, and custom SVG icons. No build step, analytics, or external font requests. A small vanilla JavaScript enhancement types the header and reveals sections once on scroll; reduced-motion preferences are respected.

Open `index.html`, or serve this directory with `python3 -m http.server 8080`.

The contact form requires PHP 8.1+ with mbstring, writable session/temp storage and a configured mail transport. Serve it locally with `php -S 127.0.0.1:8089 -t .`. It sends only to `linda@e11.consulting`; visitors cannot select recipients. Phone, email, message and consent are validated on the server. Protection includes session tokens, one-use proof of work, a honeypot, origin checks, size limits and locked hourly rate limits. No anti-spam service receives visitor data.

For integration tests without sending email, run `php -d sendmail_path=/usr/bin/true -S 127.0.0.1:8089 -t .`, then `node tools/test-contact.mjs`. This tests submission and rejection paths, not delivery. Verify delivery separately on Websupport: its native PHP mail transport requires an existing local sender mailbox. If the domain uses external email hosting, configure an authorised mail transport before enabling the form publicly. Do not commit credentials.

Publish only the website files and assets; exclude `.git`, `.qa` and `tools`. GitHub Pages cannot execute `contact.php`. Privacy text is in the footer; it describes necessary session cookies, the enquiry purpose and six-month retention. Apply that retention to enquiries in the mailbox.

GitHub Pages: select **Settings → Pages → Deploy from a branch → main → / (root)**.

The portrait and CV content belong to Linda Lukackova. Fonts retain their original licences. The supplied Oughter package states personal use only; commercial web use needs the appropriate licence from its author. Poppins is distributed under the SIL Open Font License.
