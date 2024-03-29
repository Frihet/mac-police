"""discover - network status checking tool

Discover network hosts in a network
"""
from __future__ import with_statement



# Import system libraries
import sys
import getopt
import subprocess
import re
import time
import gc

# Import email handling stuff
import smtplib
import email.mime.text
import email.mime.multipart

TIME_FORMAT = "%Y-%m-%d %H:%M:%S"
WHITELIST_FILE = "/mac-police/whitelist.csv"
EMAIL_FILE = "/mac-police/email_time.csv"
CONF_FILE = "/mac-police/mac-police.conf"

MAC_CMD = r"""ping -c 1 %s >/dev/null; cat /proc/net/arp |grep '^%s\s'|sed -ne 's/^.*\([a-zA-Z0-9][a-zA-Z0-9][:.][a-zA-Z0-9][a-zA-Z0-9][:.][a-zA-Z0-9][a-zA-Z0-9][:.][a-zA-Z0-9][a-zA-Z0-9][:.][a-zA-Z0-9][a-zA-Z0-9][:.][a-zA-Z0-9][a-zA-Z0-9]\).*/\1/p'"""

def nmap_exec(mode, network):
    return subprocess.Popen(['nmap', mode, network], stdout=subprocess.PIPE).communicate()[0]

def fetch_url(url, filename = None):
    """
    Write the contents of the specified URL to the specified file, or return it, if filename is None.
    """
    if filename is None:
        return subprocess.Popen(['wget', '-O', '-', '-q', url], stdout=subprocess.PIPE).communicate()[0]
    else:
        subprocess.Popen(['wget', '-O', filename, '-q', url], stdout=subprocess.PIPE).communicate()[0]

def send_email(host, port, use_tls, subject, recipients, sender, body_text, body_html):

    # Create the message
    msg = email.mime.multipart.MIMEMultipart("alternative")
    msg['Subject'] = subject
    msg['From'] = sender
    msg['To'] = recipients

    part1 = email.mime.text.MIMEText(body_text, 'plain')
    part2 = email.mime.text.MIMEText(body_html, 'html')
    msg.attach(part1)
    msg.attach(part2)

    # Send it
    s = smtplib.SMTP(host, port)
    if use_tls:
        s.starttls()
    s.sendmail(sender, recipients, msg.as_string())
    s.close()


class Host(object):
    """
    Object representing a specific host. 
    """

    def __init__(self, ip, name):
        self.ip=ip
        self.name=name
        self.desc = None

    def get_description(self):
        #print "Get the beef on", self.ip
        if self.desc:
            return self.desc
        output = nmap_exec('-A', self.ip).split('\n')
        res = ""
        for line in output:
            if line[0:3] not in ('SF:','==='):
                res = res + '\n' + line
        self.desc = res
        return res

    description = property(get_description)

    def get_mac(self):
        cmd = MAC_CMD % (self.ip, self.ip)
        res = subprocess.Popen(cmd, shell=True, stdout=subprocess.PIPE).communicate()[0].strip()
        if res == "":
            return None
        return res

    mac = property(get_mac)

    def __str__(self):
        return "Host(%s, %s)" % (self.ip, self.name)

    def repr(self):
        return self.__str__()


def nmap_host_discover(network):
    """
    Scan network and return list of all hosts as Host objects
    """
    output = nmap_exec('-sP', network).rsplit('\n')
    re1 = r'^Host ([^ ]*) *(\((.*)\))? *appears to be up.$'
    p1 = re.compile(re1)
    res = []
    for i in xrange(0, len(output)):
        m1 = p1.match(output[i])
        if m1:
            if m1.group(3):
                ip = m1.group(3)
                name = m1.group(1)
            else:
                ip = m1.group(1)
                name=m1.group(1)
            res.append(Host(ip, name))
    return res

def read_whitelist():
    whitelist = {}
    with open(WHITELIST_FILE) as f:
        for line in f:
            whitelist[line.strip()] = True

    return whitelist

def my_time_format(tm=None):
    if tm is None:
        tm = time.time()
    return time.strftime(TIME_FORMAT, time.localtime(tm))

def read_email_time():
    email_time = {}
    try:
      with open(EMAIL_FILE) as f:
        for line in f:
            line = line.strip()
            if line[0] == '#':
                continue
            el = map(lambda x:x.strip(), line.split(','))

            if len(el) != 2:
                print "Error while reading sent email times: Bad input line", line
                sys.exit(1)
            try:
                email_time[el[0]] = time.mktime(time.strptime(el[1], TIME_FORMAT))
            except:
                print "Error while reading sent email times: Badly formated time:", el[1]
                sys.exit(1)
    except:
  #      print "tralala", e
        # File not found is ok, we have no saved email times. 
        return {}
    return email_time

def write_email_time(email_time):
    with open(EMAIL_FILE,"w") as f:
        for (mac, tm) in email_time.iteritems():
            f.write(mac)
            f.write(", ")
            f.write(my_time_format(tm))
            f.write('\n')

            
def main():
    while True:

        print "Start scan at %s" % my_time_format()

        #Set defaults
        whitelist_url = None
        network_base = None
        network_mask = 24
        check_interval = 60*60
        email_interval = 60*60*24

        email_host='localhost'
        email_port=25
        email_use_tls=True
        email_sender='Mac-police <noreply@example.com>'
        email_recipients=None

        #Reread configuration file
        with open(CONF_FILE) as f:
            exec f

        if whitelist_url is None:
            print "No whitelist url set in config file"
            sys.exit(1)

        if network_base is None:
            print "No network set in config file"
            sys.exit(1)

        if email_recipients is None:
            print "No email recipient specified"
            sys.exit(1)

        network = network_base + "/" + str(network_mask)

        fetch_url(whitelist_url+'?action=whitelist_get', WHITELIST_FILE)
        whitelist = read_whitelist()
        email_time = read_email_time()
        #print "Searching for hosts on network", network
        top = ""
        details = ""
        top_html = ""
        details_html = ""
        now = time.time()
 #       print "Done scanning"

 #       print "Email in list:", email_time
        new_count = 0
        old_count = 0
        ok_count = 0
        ignore_count = 0
        mac_count = 0
        hosts = nmap_host_discover(network)
        for host in hosts:
            if host.mac is None:
                continue
            mac_count += 1
            if host.mac in whitelist:
                ok_count += 1
            else:
                do_send = True
                tm = now
                status = "new"
                if host.mac in email_time:
                    status = "resend"
                    if now-email_time[host.mac] > email_interval:
                        old_count += 1
                    else:
                        #print "Do not email about", host.mac
                        tm = email_time[host.mac]
                        do_send = False
                        ignore_count += 1
                else:
                    new_count += 1
                if do_send:
                    top = "%s%s\t%s\t%s\t%s\t%s?action=whitelist_add&mac=%s\n" % (top, host.mac, host.ip, host.name, status, whitelist_url, host.mac)


                    top_html = """
%s
<tr>
  <td>%s</td>
  <td>%s</td>
  <td>%s</td>
  <td>%s</td>
  <td>
    <a href='%s?action=whitelist_add&mac=%s'>Whitelist</a>
  </td>
</tr>""" % (top_html, host.mac, host.ip, host.name, status, whitelist_url, host.mac)
                    details = "%sScan results for %s:\n%s\n\n" % (details, host.mac, host.description)
                                              
                email_time[host.mac] = tm

        if top != '':
            header= "Summary\n"
            header_html = """
<h1>Summary</h2>
Found %d network interfaces. Of these, %d are whitelisted, %d are previously scanned and ignored, %d are reminders of previously scanned interfaces and %d are new.
<table>
<tr><th>MAC</th><th>IP</th><th>Hostname</th><th>Status</th><th></th></tr>
"""  % (mac_count, ok_count, ignore_count, old_count, new_count)
            body_html = header_html + top_html + """</table>
<h2>Details</h2>
<pre>""" + details + "</pre>"

            body_text = header+top+details

            subject = "Mac-police report: %d new and %d old interfaces found" % (new_count, old_count)
            
            send_email(email_host, email_port, email_use_tls, subject, email_recipients, email_sender, body_text, body_html)
        
        write_email_time(email_time)
        print "Finished scan at %s" % my_time_format()

        # Erase a few variable and gc since we should be sleeping a
        # long time and it would be nice to reduce memory usage a bit.
        body_html = None
        body_text = None
        top = None
        top_html = None
        subject=None
        details = None
        header_html = None
        email_time=None
        whitelist = None
        hosts=None
        gc.collect()
        
        time.sleep(check_interval)
        

if __name__ == "__main__":
    main()
