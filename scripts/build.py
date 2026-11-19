"""Construye el ZIP de evaluación a partir de los archivos versionados."""

import argparse
from hashlib import sha256
from pathlib import Path
import subprocess
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
    versionados = subprocess.check_output(["git", "ls-files", "-z"], cwd=base).decode("utf-8")
    seleccion = {archivo for archivo in versionados.split("\0") if archivo in ARCHIVOS
                 or archivo.split("/", 1)[0] in CARPETAS}
    if faltan := (ARCHIVOS | REQUERIDOS) - seleccion:
        raise SystemExit("Faltan archivos versionados en el paquete: " + ", ".join(sorted(faltan)))
    manifiesto = {}
    try:
        with ZipFile(temporal, "w", compression=ZIP_DEFLATED, compresslevel=9) as zip_file:
            for nombre in sorted(seleccion):
                ruta = base / nombre
                if ruta.is_symlink() or ruta.resolve().is_relative_to(base) is False:
                    raise ValueError("Ruta fuera del proyecto: " + nombre)
                contenido = ruta.read_bytes()
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
