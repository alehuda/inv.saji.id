#!/usr/bin/env python3
# Membuat halaman LIVE dari index.html: live/index.html (Beranda, alamat /) dan live/item/index.html (Daftar Item, alamat /item/).
# Halaman LIVE tidak memuat data item sama sekali: data datang dari server setelah login.
import os,re
src=open("index.html",encoding="utf-8").read()

def strip_literal(t,name,open_c,close_c):
    """Ganti isi 'var NAME=<literal>;' dengan literal kosong, dengan memperhatikan tanda kutip."""
    m=re.search(r"var %s="%name,t)
    assert m,name
    i=m.end(); assert t[i]==open_c,(name,t[i])
    depth=0;q=None;j=i
    while True:
        c=t[j]
        if q:
            if c=="\\": j+=1
            elif c==q: q=None
        elif c in "\"'": q=c
        elif c==open_c: depth+=1
        elif c==close_c:
            depth-=1
            if depth==0: break
        j+=1
    return t[:i]+open_c+close_c+t[j+1:]

src=strip_literal(src,"REAL","[","]")
src=strip_literal(src,"HALALD","{","}")

def make(start):
    cfg='<script>window.SAJI_LIVE={start:"%s",api:"/"};</script>\n'%start
    i=src.index("<script>")
    t=src[:i]+cfg+src[i:]
    t=t.replace("<title>Mockup Inventory Saji</title>","<title>Inventory Saji</title>")
    return t
for path,start in(("live/index.html","home"),("live/item/index.html","items")):
    os.makedirs(os.path.dirname(path),exist_ok=True)
    open(path,"w",encoding="utf-8").write(make(start))
    print("dibuat",path,os.path.getsize(path))
