# Imagen base con Node
FROM node:20

WORKDIR /app

# Copiar package.json (sin package-lock para evitar problemas de plataforma)
COPY package.json ./

# Limpiar e instalar dependencias, forzando rollup para linux
RUN rm -rf node_modules package-lock.json && \
    npm install && \
    npm install @rollup/rollup-linux-x64-gnu --save-optional

# Copiar entrypoint y convertir line endings
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh && sed -i 's/\r$//' /entrypoint.sh

# Exponer puerto
EXPOSE 5173

ENTRYPOINT ["/entrypoint.sh"]

