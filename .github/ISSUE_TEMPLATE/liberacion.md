---
name: Liberación de versión
about: Lista de verificación antes de publicar vX.Y.Z en producción
labels: liberacion
---

**Versión:** vX.Y.Z

- [ ] `release/vX.Y.Z` creada desde `develop` y publicada en staging
- [ ] CI en verde (revisión, pruebas y entorno de liberación)
- [ ] Pruebas manuales en staging (celular y computadora)
- [ ] Migraciones probadas en staging
- [ ] `CHANGELOG.md` actualizado
- [ ] Fusionada en `main` y etiquetada `vX.Y.Z`
- [ ] Despliegue aprobado y prueba de humo en verde
- [ ] Monitoreo sin alertas 30 minutos después
