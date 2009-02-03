"""discover - network status checking tool

Discover network hosts in a network
"""
from __future__ import with_statement

import sys
import getopt
import subprocess
import re

def nmapExec(mode, network):
    return subprocess.Popen(['nmap', mode, network], stdout=subprocess.PIPE).communicate()[0]


class Host(object):

    def __init__(self, ip, name, mac):
        self.ip=ip
        self.name=name
        self.mac=mac
        self.desc = None

    def get_description(self):
        if self.desc:
            return self.desc
        output = nmapExec('-A', self.ip).rsplit('\n')
        res = ""
        for line in output:
            if line.substr[0:3] in ('SF:','==='):
                res = res + line
        return res

    description = property(get_description)

    def __str__(self):
        return "Host(%s, %s, %s)" % (self.ip, self.name, self.mac)

    def repr(self):
        return self.__str__()


def nmapHostDiscover(network):
    output = nmapExec('-sP', network).rsplit('\n')
    re1 = r'^Host ([^ ]*) *(\((.*)\))? *appears to be up.$'
    re2 = r'^MAC Address: ([^ ]*) *(\(.*\))?$'
    p1 = re.compile(re1)
    p2 = re.compile(re2)
    res = []
    for i in xrange(0, len(output)-1):
        m1 = p1.match(output[i])
        m2 = p2.match(output[i+1])
        if m1 and m2:
            mac = m2.group(1)
            if m1.group(3):
                ip = m1.group(3)
                name = m1.group(1)
            else:
                ip = m1.group(1)
                name=m1.group(1)
            res.append(Host(ip, name, mac))
    return res

def read_whitelist():
    whitelist = {}
    with open("/mac-police/whitelist.csv") as f:
        for line in f:
            whitelist[line.strip()] = True

    return whitelist


def main():
    network = '10.0.10.0/24'
    whitelist = read_whitelist()
    email_time = read_email_time()
    print "Searching for hosts on network", network
    top = ""
    details = ""
    email_time_out = {}
    now = time.time()
    for host in nmapHostDiscover(network):
        if not host.mac in whitelist:
            do_send = True
            if host.mac in email_time:
                tm = email_time[host.mac]
                if now-tm > 
                
            email_time_out[host.mac] = tm
            



if __name__ == "__main__":
    main()
