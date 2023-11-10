FROM node:14

WORKDIR /app

COPY ./laravel-echo-server.json /app

RUN npm install -g laravel-echo-server

EXPOSE 6001

CMD ["laravel-echo-server", "start"]
