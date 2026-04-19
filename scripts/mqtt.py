#!/usr/bin/env python3
import paho.mqtt.client as mqtt
import sys
import signal

# Configurações do broker e tópico
BROKER = "localhost"  # Pode ser seu broker local ou na nuvem
PORT = 1883
#TOPIC = "102b/entrada"
TOPIC = "#"

# Função chamada quando conecta ao broker
def on_connect(client, userdata, flags, rc):
    if rc == 0:
        print(f"Conectado ao broker {BROKER}")
        client.subscribe(TOPIC)
        print(f"Inscrito no tópico: {TOPIC}")
    else:
        print(f"Falha na conexão. Código: {rc}")

# Função chamada quando recebe mensagem
def on_message(client, userdata, msg):
    print(f"[{msg.topic}] {msg.payload.decode()}")

# Função para encerrar com Ctrl+C
def signal_handler(sig, frame):
    print("\nEncerrando listener MQTT...")
    client.disconnect()
    sys.exit(0)

# Configura o cliente MQTT
client = mqtt.Client()
client.on_connect = on_connect
client.on_message = on_message

# Conecta ao broker
try:
    client.connect(BROKER, PORT, keepalive=60)
except Exception as e:
    print(f"Erro ao conectar: {e}")
    sys.exit(1)

# Captura Ctrl+C
signal.signal(signal.SIGINT, signal_handler)

# Loop infinito para ouvir mensagens
client.loop_forever()
