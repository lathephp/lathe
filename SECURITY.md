# Security policy

## Reporting a vulnerability

Please do not open a public issue for a security problem. Report it privately through either channel:

- GitHub private vulnerability reporting: the "Report a vulnerability" button on the Security tab of [lathephp/lathe](https://github.com/lathephp/lathe/security/advisories/new).
- Email: security@lathephp.com.

Say which version you use, how to reproduce the problem and what an attacker gains.

A confirmed critical vulnerability is fixed and released within 7 days. Each fix is published as a GitHub Security Advisory, which `composer audit` reads.

## Supported versions

The latest major version gets bug fixes and security fixes. The previous major version gets security fixes for 12 months after the next major version ships.

| Version | Bug fixes | Security fixes |
|---|---|---|
| 1.x (including the 1.0.0 betas) | Yes | Yes |

## What Lathe does not do

Lathe makes no outbound call from the running panel: no update check, no telemetry and no licence check. `lathe:preset` is the only command that uses the network, and only when you run it in a terminal.
