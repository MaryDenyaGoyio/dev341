import os
import sys

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
venv_python = os.path.join(os.path.dirname(BASE_DIR), "env/bin/python")

if sys.executable != venv_python:
    os.execv(venv_python, [venv_python] + sys.argv)