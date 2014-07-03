from flask import Flask
from flask import request
import getopt
import sys
import json
import subprocess
import traceback
import transform

app = Flask(__name__)

@app.route("/")
def hello():
    try:
        config_file = request.args.get('config_file')
        input_file = request.args.get('input_file')
        output_file = request.args.get('output_file')
        creation_time = request.args.get('creation_time')
        transform.run(config_file, input_file, output_file, creation_time)

        return "lalala"
    except:
        traceback.print_exc()
        return "lelele"

if __name__ == "__main__":
    app.debug = True
    app.run()

