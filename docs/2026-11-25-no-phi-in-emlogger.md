# Keep MRNs and EHR values out of em_logger (2026-11-25)

## Problem

- **`http_request()` in `classes/DDP_utilities.php`.** When the STARR call failed (curl error or a non-200), it `emLog`ged the raw response body at INFO, which always ships. STARR's error responses can echo the request (the MRN) or patient values.
- **`pages/DDP_data_service.php` debug lines.** One logged the MRN (`$id`), one the full request sent to Vertx (MRN included), and one the full results: the EHR values returned for the patient.

## Fix

- The failure path now logs one `emError` line with the HTTP status, the response size and the curl error. That's arguably the right level for a failure anyway.
- The three debug lines log the field count and the response size instead.
- Return values and behavior are unchanged.

## How it was reproduced and verified (local, REDCap 17.5.9)

STARR, the IRB Lookup API and the Vertx token service can't be reached from a local stack. So the module's own `http_request()` was run in module context against a local mock STARR endpoint that returns fake values. Each run then checked `DDP.json` for the fake MRN.

| Code | Mock returns HTTP 500 | Mock returns HTTP 200 |
| --- | --- | --- |
| `master` | 1 INFO line with the MRN | nothing logged |
| this branch | 0 lines with the MRN (1 ERROR line: status + size) | nothing logged |

The three `DDP_data_service.php` lines sit behind the IRB check and the token lookup, so they could not be run locally. They were changed by review and checked with `php -l`.

Scripts: `emlogger-phi-review/e2e/ddp-http.php` and `mock-starr.php` in the REDCap EM workspace.
