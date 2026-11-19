"""Construye el ZIP de evaluación a partir del commit HEAD."""

import argparse
from hashlib import sha256
from io import BytesIO
from pathlib import Path
import subprocess
from tarfile import open as open_tar
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo


RAIZ = "SIPE-grupo-5/"
NOMBRE = "SIPE-GRUPO-5-ENTREGA-FINAL.zip"
CARPETAS = {"app", "assets", "database", "documentacion", "public", "vendor"}
ARCHIVOS = {
    ".htaccess", "index.php", "README.md", "LEEME-PRIMERO.txt", "composer.json", "composer.lock",
    "config/example.php", "config/protection-check.txt", "docs/protection-check.txt",
    "var/protection-check.txt",
}
REQUERIDOS = {
    "database/datos-academicos.json", "database/exportacion-academica.sql",
    "database/entrega-plan.json", "database/referencias-pendientes.json",
    "documentacion/GUIA-DE-INSTALACION.md", "documentacion/MANUAL-DE-USUARIO.md",
    "documentacion/ARQUITECTURA.md", "documentacion/BASE-DE-DATOS.md",
    "documentacion/PRUEBAS.md", "vendor/autoload.php",
}


def incluir(nombre):
    ruta = Path(nombre)
    if nombre in ARCHIVOS:
        return True
    return (ruta.parts[0] in CARPETAS
            and not any(p.startswith(".") for p in ruta.parts)
            and not ruta.name.endswith((".local.php", ".log", ".tmp", ".bak", ".zip", "~")))


def entrada(nombre):
    archivo = ZipInfo(RAIZ + nombre, (1980, 1, 1, 0, 0, 0))
    archivo.compress_type = ZIP_DEFLATED
    archivo.external_attr = 0o100644 << 16
    return archivo


def main():
    base = Path(__file__).resolve().parents[1]
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--salida", type=Path, default=base / "dist")
    args = parser.parse_args()
    args.salida.mkdir(parents=True, exist_ok=True)
    resultado = args.salida / NOMBRE
    temporal = resultado.with_suffix(".tmp")
    versionados = subprocess.check_output(["git", "archive", "--format=tar", "HEAD"], cwd=base)
    manifiesto = {}
    try:
        with open_tar(fileobj=BytesIO(versionados)) as repo, ZipFile(
            temporal, "w", compression=ZIP_DEFLATED, compresslevel=9
        ) as zip_file:
            seleccion = {ruta.name: ruta for ruta in repo if not ruta.isdir() and incluir(ruta.name)}
            if faltan := (ARCHIVOS | REQUERIDOS) - seleccion.keys():
                raise SystemExit("Faltan archivos en el commit: " + ", ".join(sorted(faltan)))
            for nombre in sorted(seleccion):
                if not seleccion[nombre].isfile():
                    raise ValueError("El paquete requiere un archivo regular: " + nombre)
                with repo.extractfile(seleccion[nombre]) as archivo:
                    contenido = archivo.read()
                manifiesto[nombre] = sha256(contenido).hexdigest()
                zip_file.writestr(entrada(nombre), contenido, compresslevel=9)
            texto = "".join(f"{digest}  {nombre}\n" for nombre, digest in manifiesto.items())
            zip_file.writestr(entrada("documentacion/SHA256SUMS.txt"), texto.encode("utf-8"))
        with ZipFile(temporal) as zip_file:
            if zip_file.testzip() or any(
                sha256(zip_file.read(RAIZ + nombre)).hexdigest() != digest
                for nombre, digest in manifiesto.items()
            ):
                raise RuntimeError("El ZIP no coincide con los archivos de origen.")
        temporal.replace(resultado)
    finally:
        temporal.unlink(missing_ok=True)
    digest = sha256(resultado.read_bytes()).hexdigest()
    resultado.with_suffix(".zip.sha256").write_text(f"{digest}  {NOMBRE}\n", encoding="ascii")
    print(f"{resultado}\n{resultado.stat().st_size} bytes\nSHA-256 {digest}")


if __name__ == "__main__":
    main()
