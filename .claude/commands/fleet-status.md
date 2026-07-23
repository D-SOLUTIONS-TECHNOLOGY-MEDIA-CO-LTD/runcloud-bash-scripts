Check the status of all 20 production servers: script version and SSH connectivity.

Steps:
1. Read server list from `sqlite3 ~/.rc/rc.db "SELECT hostname, ssh_port FROM servers ORDER BY hostname;"`
2. In parallel, SSH to each server and run: `cd /root/runcloud-bash-scripts && git log --oneline -1`
3. Report: table of server | status | git commit
