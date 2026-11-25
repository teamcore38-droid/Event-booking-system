import re, io, uuid, zipfile, pathlib, datetime, concurrent.futures
import requests, pymupdf, cv2, numpy as np
base='http://127.0.0.1:8186/'
serial=uuid.uuid4().hex[:12]
def get(s,p): return s.get(base+p,timeout=20)
def submit(s,p,action,files=None,**kw):
    page=get(s,p)
    csrf=re.search(r'name="csrf" value="([^"]+)"',page.text)[1]
    return s.post(base+p,data=dict(action=action,csrf=csrf,**kw),files=files,timeout=25)
def expect(r,phrase):
    assert phrase in r.text, (r.url,phrase,re.sub('<[^>]+>',' ',r.text)[-1500:])
def login(role):
    line=next(x for x in pathlib.Path('var/local-accounts.txt').read_text().splitlines() if role+'@' in x)
    email,password=line.split()[-2:]
    s=requests.Session()
    expect(submit(s,'?p=login','login',email=email,password=password),'Sign out')
    return s
admin=login('admin')
staff=login('staff')
p1,p2=requests.Session(),requests.Session()
for i,s in enumerate((p1,p2)):
    expect(submit(s,'?p=register','register',name='Verification',email=f'{serial}-{i}@test.invalid',password='test-ticket-123'),'My tickets')
from PIL import Image
event_fields=dict(title='QA '+serial,genre='QA',venue='Test hall',starts=(datetime.date.today()+datetime.timedelta(days=2)).isoformat()+'T19:00',description='Temporary test fixture',art='art')
def upload(fmt, color):
    buf=io.BytesIO()
    Image.new('RGB',(1800,900),color).save(buf,format=fmt)
    return {'cover':('cover.'+fmt.lower(),buf.getvalue(),'image/'+fmt.lower())}
e=submit(admin,'?p=edit','event',files=upload('PNG','purple'),**event_fields)
expect(e,'Ticket categories');eid=int(e.url.split('id=')[-1])
edit=f'?p=edit&id={eid}'
cover_url=f'event-cover.php?id={eid}'
def cover_image():
    result=get(p1,cover_url)
    assert result.headers['Content-Type']=='image/jpeg'
    return Image.open(io.BytesIO(result.content))
assert cover_image().size==(1600,800)
assert cover_image().getpixel((5,5))[2]>100
expect(submit(admin,edit,'event',id=eid,**event_fields),'Remove uploaded image')
assert cover_image().size==(1600,800)
expect(submit(admin,edit,'event',id=eid,files={'cover':('invalid.png',b'not an image','image/png')},**event_fields),'Choose a JPEG')
expect(submit(admin,edit,'event',id=eid,remove_cover='on',**event_fields),'Save event')
assert get(p1,cover_url).url.endswith('assets/art.jpg')
expect(submit(admin,edit,'event',id=eid,files=upload('JPEG','green'),**event_fields),'Remove uploaded image')
expect(submit(admin,edit,'event',id=eid,files=upload('WEBP','blue'),**event_fields),'Remove uploaded image')
assert cover_image().getpixel((5,5))[2]>240
expect(submit(admin,edit,'category',id=eid,name='Test seat',capacity='1',price='1400.00',numbered='on'),'Test seat')
event=f'?p=event&id={eid}'
def book(s):
    html=get(s,event).text
    cid=re.search('name="category" value="(\\d+)"',html)[1]
    key=re.search('name="request_key" value="(\\w+)"',html)[1]
    token=re.search('name="csrf" value="([^"]+)"',html)[1]
    return dict(id=eid,category=cid,request_key=key,seat=1,action='book',csrf=token)
payloads=[book(s) for s in (p1,p2)]
def post(args):
    s,data=args
    return s.post(base+event,data=data,timeout=20)
with concurrent.futures.ThreadPoolExecutor() as ex: results=list(ex.map(post,zip((p1,p2),payloads)))
assert sum('Confirmed ticket #' in r.text for r in results)==1
winner=0 if 'Confirmed ticket #' in results[0].text else 1
expect(results[1-winner],'sold out')
s=(p1,p2)[winner];ticket=results[winner]
expect(ticket,cover_url)
expect(get(s,'?p=mine'),cover_url)
expect(get(s,'?q='+serial),cover_url)
expect(get(s,event),cover_url)
code=re.search(r'<code>([0-9A-F]+)</code>',ticket.text)[1];bid=int(ticket.url.split('id=')[-1])
expect(post((s,payloads[winner])),f'Confirmed ticket #{bid}')
expect(get((p1,p2)[1-winner],f'?p=ticket&id={bid}'),'Ticket unavailable')
check=f'?p=check&id={eid}'
expect(submit(staff,check,'admit',id=eid,code=code),'Payment is pending')
expect(submit(admin,'?p=reports','paid',id=bid),'QA '+serial)
expect(submit(staff,check,'admit',id=eid,code=code),'Ticket accepted')
expect(submit(staff,check,'admit',id=eid,code=code),'Already checked in')
assert get(staff,'?p=reports').status_code==403
pdf=get(s,f'?p=download&id={bid}')
doc=pymupdf.open(stream=pdf.content,filetype='pdf')
cover_embedded=False
for info in doc[0].get_images():
    img=Image.open(io.BytesIO(doc.extract_image(info[0])['image']))
    if img.size==(1600,800) and img.convert('RGB').getpixel((5,5))[2]>240: cover_embedded=True
assert cover_embedded,'Uploaded cover missing from ticket PDF'
pix=doc[0].get_pixmap(matrix=pymupdf.Matrix(2,2))
rgb=np.frombuffer(pix.samples,np.uint8).reshape(pix.height,pix.width,3)
decoded=cv2.QRCodeDetector().detectAndDecode(rgb)[0]
assert decoded==code,(decoded,code)
xlsx=get(admin,f'?p=export&id={eid}&format=xlsx')
with zipfile.ZipFile(io.BytesIO(xlsx.content)) as f: assert b'QA '+serial.encode() in f.read('xl/sharedStrings.xml')
report=get(admin,f'?p=export&id={eid}&format=pdf')
assert 'QA '+serial in pymupdf.open(stream=report.content,filetype='pdf')[0].get_text()
print('PASS: roles, registration, event creation, reserved seats, simultaneous last-seat booking, retry, ownership, payments, check-in, duplicate rejection, QR decode from PDF, XLSX and PDF reports.')
import subprocess
subprocess.run(['php','tests/remove-fixture.php',serial,str(eid)],check=True)
