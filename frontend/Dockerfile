# Dockerfile for Vue 3 (Vite) frontend
FROM node:20-alpine
WORKDIR /app
COPY package.json .
# COPY package-lock.json .
RUN npm install --force
COPY . .
RUN npm run build
EXPOSE 8080
CMD ["npm", "run", "start"]
