Deploy the latest bash scripts to all 20 production servers.

Steps:
1. Run `git push` if there are unpushed commits
2. Deploy scripts to all servers in parallel: read server list from `sqlite3 ~/.rc/rc.db "SELECT hostname, ssh_port FROM servers;"`, SSH to each, `git checkout -- . && git pull --ff-only && chmod +x *.sh`
3. Report results: count successful/failed servers
