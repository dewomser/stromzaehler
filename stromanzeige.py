#! /usr/bin/env python
# -*- coding: utf-8 -*-
# vi:ts=4:et
from Tkinter import *
import pycurl
try:
    from io import BytesIO
except ImportError:
    from StringIO import StringIO as BytesIO

buffer = BytesIO()
c = pycurl.Curl()
c.setopt(c.URL, 'http://192.168.1.6')
c.setopt(c.WRITEDATA, buffer)
# For older PycURL versions:
#c.setopt(c.WRITEFUNCTION, buffer.write)
c.perform()
c.close()

body = buffer.getvalue()
# Body is a string on Python 2 and a byte string on Python 3.
# If we know the encoding, we can always decode the body and
# end up with a Unicode string.
#print(body.decode('iso-8859-1'))


root = Tk()

w = Label(root, text= body.decode('iso-8859-1'), padx = 40, pady = 40)
w.pack()

root.mainloop()
