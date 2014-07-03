#!/usr/bin/python3
# -*- coding: utf-8 -*-
import json
import pprint
import os

print("Content-type: text/plain; utf-8")
print("")

#pprint.pprint(os.environ)
#os.environ['LC_ALL'] = 'en_US.UTF-8'
data = open('/usr/local/share/config.json', encoding="utf-8").read()
#pprint.pprint(data.encode('utf-8'))
print(data)
decoded = json.loads(data)
print(decoded)
