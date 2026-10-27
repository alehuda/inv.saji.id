#!/usr/bin/env python3
# Membuat halaman LIVE dari index.html: live/index.html (Beranda, alamat /) dan live/item/index.html (Daftar Item, alamat /item/).
import os,re
src=open("index.html",encoding="utf-8").read()
ROOM="item-8f4k2m"
def make(start):
    cfg='<script>window.SAJI_LIVE={start:"%s",room:"%s",api:"/"};</script>\n'%(start,ROOM)
    i=src.index("<script>")
    t=src[:i]+cfg+src[i:]
    t=t.replace("<title>Mockup Inventory Saji</title>","<title>Inventory Saji</title>")
    return t
for path,start in(("live/index.html","home"),("live/item/index.html","items")):
    os.makedirs(os.path.dirname(path),exist_ok=True)
    open(path,"w",encoding="utf-8").write(make(start))
    print("dibuat",path)
