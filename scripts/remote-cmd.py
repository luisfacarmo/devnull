"""Execute a command on the server via SSH over Bluetooth PAN."""
import sys
import paramiko

HOST = '10.0.0.1'
USER = 'mulder'
PASS = 'LFAdC992767653@'

cmd = sys.argv[1] if len(sys.argv) > 1 else 'hostname'

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, username=USER, password=PASS, timeout=30)

# Use sudo with password piped via stdin
full_cmd = f'echo "{PASS}" | sudo -S bash -c "{cmd}" 2>/dev/null'
stdin, stdout, stderr = client.exec_command(full_cmd, timeout=25)
out = stdout.read().decode()
err = stderr.read().decode()

if out:
    print(out)
if err:
    print(f"STDERR: {err}", file=sys.stderr)

client.close()
