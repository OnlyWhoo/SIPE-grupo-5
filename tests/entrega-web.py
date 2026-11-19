"""Ensayo HTTP de una extracción limpia del ZIP académico en XAMPP."""

import argparse
import base64
import subprocess
from datetime import datetime, timedelta, timezone
import html
from http.cookiejar import CookieJar
import json
from pathlib import Path
import re
import secrets
from urllib.error import HTTPError
from urllib.parse import urlencode
from urllib.request import HTTPCookieProcessor, Request, build_opener


def client():
    return build_opener(HTTPCookieProcessor(CookieJar()))


def request(browser, url, fields=None, raw=None, content_type=None):
    body = urlencode(fields).encode() if fields is not None else raw
    headers = {"Content-Type": content_type} if content_type else {}
    try:
        response = browser.open(Request(url, body, headers), timeout=25)
    except HTTPError as error:
        response = error
    with response:
        return response.status, response.url, response.headers, response.read()


def check(value, description):
    if not value:
        raise SystemExit(f"FALLO {description}")
    print(f"OK {description}", flush=True)


def csrf(page):
    return re.search(rb'name="csrf" value="([a-f0-9]+)"', page)[1].decode()


def text(response):
    return html.unescape(response[3].decode("utf-8"))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("url")
    parser.add_argument("base_datos")
    parser.add_argument("php", type=Path)
    args = parser.parse_args()
    base = args.url.rstrip("/") + "/"
    if not re.fullmatch(r"http://127\.0\.0\.1/(?:_sipe-grupo5-qa-[a-z0-9]+/)?SIPE-grupo-5/", base):
        raise SystemExit("Usa una extracción de prueba de SIPE-grupo-5 en localhost.")
    if not re.fullmatch(r"sipe_grupo_5_qa_[a-z0-9]+", args.base_datos):
        raise SystemExit("Usa una base de prueba nueva sipe_grupo_5_qa_*.")
    root = Path(__file__).resolve().parents[1]
    snapshot = json.loads((root/"database/datos-academicos.json").read_text(encoding="utf-8"))
    def rows(name):
        table = snapshot["tablas"][name]
        return [dict(zip(table["columnas"], row)) for row in table["filas"]]

    http = client()
    def get(route, **params):
        return request(http, base+"index.php?"+urlencode({"r":route, **params}))
    def post(route, fields, **params):
        return request(http, base+"index.php?"+urlencode({"r":route,**params}), {"csrf":token,**fields})

    setup = request(http, base)
    check(setup[0]==200 and "Preparar SIPE Grupo 5" in text(setup), "asistente en la ruta de la nueva carpeta")
    for path in ("database/datos-academicos.json","database/exportacion-academica.sql",
                 "documentacion/protection-check.txt","vendor/autoload.php","config/example.php"):
        check(request(http,base+path)[0]==403, "Apache protege "+path)
    password = secrets.token_urlsafe(24)
    correo = snapshot["administrador"]["correo"]
    token = csrf(setup[3])
    params = {"host":"127.0.0.1","port":"3306","database":args.base_datos,"user":"root",
              "password":"","admin_password":password}
    denied = request(http, base, params)
    check(denied[0]==403, "asistente exige CSRF")
    forbidden = request(http, base, params | {"csrf":token,"database":"sipe_v2"})
    check("Elige sipe_grupo_5" in text(forbidden), "el asistente rechaza la base original de SIPE V2")
    login = request(http, base, params | {"csrf":token})
    check(login[0]==200 and "r=login" in login[1], "asistente restaura la base y abre el inicio de sesión")
    subprocess.run([str(args.php),str(root/"tests/verificar-copia.php"),args.base_datos],check=True)
    check("r=login" in get("photo",id=1)[1] and "r=login" in get("qr",id=1)[1],
          "foto y QR requieren sesión")
    token = csrf(login[3])
    dashboard = post("login", {"correo":correo,"password":password})
    check(dashboard[0]==200 and "r=dashboard" in dashboard[1], "inicio de sesión con la nueva contraseña de evaluación")
    token = csrf(dashboard[3])

    blocked_ids={row["estudiante_id"] for row in rows("sanciones") if row["estado"]=="ABIERTA"}
    people=rows("estudiantes")
    by_id={p["id"]:p for p in people}
    fotos={row["estudiante_id"]:row for row in rows("fotografias_prestatarios")}
    sample={role:next(p for p in people if p["tipo_persona"]==role and p["activo"]=="1"
                      and p["id"] not in blocked_ids and p["id"] in fotos)
            for role in ("ESTUDIANTE","DOCENTE","ADMINISTRATIVO")}
    student=sample["ESTUDIANTE"]
    teacher=sample["DOCENTE"]
    admin=sample["ADMINISTRATIVO"]
    for person,identity in ((student,student["codigo_estudiante"]),(teacher,teacher["qr_token"]),
                            (admin,admin["codigo_estudiante"])):
        result=post("borrower",{"identity":identity})
        check(result[0]==200 and f"person={person['id']}" in result[1] and person["tipo_persona"] in text(result),
              f"identificación y ficha de {person['tipo_persona']}")
    profile=text(get("student",id=student["id"]))
    check(student["codigo_estudiante"] in profile and 'class="borrower-profile-photo"' in profile
          and "Historial de préstamos" in profile,"hoja de vida con fotografía e historial")
    check(get("photo",id=student["id"])[3]==base64.b64decode(fotos[student["id"]]["contenido"],validate=True),
          "fotografía entregada por Apache corresponde exactamente al prestatario")
    check(b"<svg" in get("qr",id=student["id"])[3],"QR SVG generado con el token restaurado")
    missing=next(p for p in people if p["id"] not in fotos)
    check(get("photo",id=missing["id"])[3]==(root/"assets/img/foto-generica.png").read_bytes(),
          "la ficha sin foto usa la imagen genérica original")

    blocked=by_id[next(iter(blocked_ids))]
    page=text(get("borrower",person=blocked["id"]))
    check("BLOQUEADO POR SISTEMAS" in page and re.search(r"<button disabled[^>]*>INGRESAR",page),
          "sanción abierta restaurada bloquea el formulario")
    selection=get("resources",estudiante_id=blocked["id"],grupo_trabajo=blocked["tipo_persona"],tipo_prestamo="INTERNO")
    check(selection[0]==422 and "sanción activa" in text(selection), "ruta directa continúa bloqueada")
    sanction=next(s for s in rows("sanciones") if s["estudiante_id"]==blocked["id"] and s["estado"]=="ABIERTA")
    found=post("sanctions",{"action":"search","code":blocked["codigo_estudiante"]})
    check("Usuario encontrado" in text(found), "sanciones busca al prestatario por código")
    resolved=request(http,found[1],{"csrf":token,"action":"resolve","id":sanction["id"],
                                    "estado":"RESUELTA","observaciones":"Prueba aislada de restauración"})
    check("Resolución registrada" in text(resolved) and "BLOQUEADO POR SISTEMAS" not in text(get("borrower",person=blocked["id"])),
          "resolver la sanción restaura el acceso")

    active_categories={row["id"] for row in rows("categorias_recursos") if row["activo"]=="1"}
    available=[r for r in rows("recursos") if r["activo"]=="1" and r["estado_operativo"]=="DISPONIBLE"
               and r["estado_fisico"]!="DANADO" and r["categoria_id"] in active_categories]
    r1=available[0]
    r2=next(r for r in available if r["categoria_id"]!=r1["categoria_id"])
    r3=next(r for r in available if r not in (r1,r2) and r["categoria_id"]!=r1["categoria_id"])
    zone=timezone(timedelta(hours=-5))
    now=datetime.now(zone)
    future=(now.date()+timedelta(days=1)).isoformat()
    start={"day":future,"time":"14:00","recurso_id":r1["id"],"estudiante_id":student["id"],
           "tipo_prestamo":"INTERNO","grupo_trabajo":"ESTUDIANTE"}
    check(get("resources",**start)[0]==200,"selección y disponibilidad")
    check(post("confirm",start)[0]==422,"estudiante sin autorización académica rechazado")
    grant=post("authorizations",{"programa_id":student["programa_id"],"categoria_id":r1["categoria_id"],"permitir":"1"})
    check(grant[0]==200,"alta de autorización académica únicamente en el ensayo")
    check("20:00" in text(post("confirm",start)),"préstamo interno propone devolución a las 20:00")
    check("Reserva confirmada" in text(post("confirm",start|{"action":"reserve"})),"reserva estudiantil autorizada")
    check(post("confirm",start|{"recurso_id":r2["id"],"action":"reserve"})[0]==422,
          "estudiante sigue limitado a su categoría")
    teacher_payload=start|{"estudiante_id":teacher["id"],"grupo_trabajo":"DOCENTE","recurso_id":r2["id"],
                           "tipo_prestamo":"EXTERNO","fin":future+"T16:30","action":"reserve"}
    check("Reserva confirmada" in text(post("confirm",teacher_payload)),
          "docente reserva otra categoría sin aprobación")
    check(post("confirm",teacher_payload)[0]==422,"servidor conserva la validación de conflictos")
    loan_payload=start|{"estudiante_id":admin["id"],"grupo_trabajo":"ADMINISTRATIVO","recurso_id":r3["id"],
                        "tipo_prestamo":"EXTERNO","action":"reserve","day":now.date().isoformat(),
                        "time":now.strftime("%H:%M"),"fin":(now+timedelta(hours=1)).strftime("%Y-%m-%dT%H:%M")}
    check("Reserva confirmada" in text(post("confirm",loan_payload)),
          "administrativo reserva otra categoría sin aprobación")
    last_r=max(int(r["id"]) for r in rows("reservas"))
    last_l=max(int(r["id"]) for r in rows("prestamos"))
    check("La reserva quedó atendida" in text(post("deliver",{"id":last_r+3,"estado_fisico":"BUENO"})),
          "entrega física de la reserva administrativa")
    check("Vale de préstamo externo" in text(get("voucher",id=last_l+1)),"vale de préstamo externo")
    check("Devolución registrada" in text(post("return-loan",{
          "id":last_l+1,"estado_fisico":"BUENO","apto":"1"})),"devolución e inspección")
    check("Solicitud cancelada" in text(post("requests",{"id":last_r+2,"action":"cancel"})),"cancelación con historial")
    check("Historial por prestatario" in text(get("reports",report="historial",identity=admin["codigo_estudiante"])),
          "reporte por prestatario")
    check("DEVOLVER" in text(get("audit")),"auditoría registra la devolución")
    check("r=login" in post("logout",{})[1],"cierre de sesión")
    check("r=login" in request(client(),base)[1],"la aplicación instalada abre de nuevo sin resembra")


if __name__=="__main__":
    main()
