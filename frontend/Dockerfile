# Dockerfile for Vue 3 (Vite) frontend
FROM node:20-alpine
WORKDIR /app
COPY package.json .
# COPY package-lock.json .
RUN npm install --force
COPY . .
ARG VITE_API_BASE_URL
RUN VITE_API_BASE_URL=${VITE_API_BASE_URL} npm run build
EXPOSE 8080
CMD ["npm", "run", "start"]
