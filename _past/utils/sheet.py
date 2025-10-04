import os
import sys
import json

import gspread
from google.oauth2.service_account import Credentials
from googleapiclient.discovery import build

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
json_file_path = os.path.join(BASE_DIR, "macro-context-402518-b6ed54fbde7b.json")
scope = ["https://www.googleapis.com/auth/spreadsheets"]
credentials = Credentials.from_service_account_file(json_file_path, scopes=scope)

spreadsheet_url = "https://docs.google.com/spreadsheets/d/1XNuI5feorqsC__FU8gNhhnoglUlm8WbCVmC2HRX7wUk/edit?gid=747312861#gid=747312861"
gc = gspread.service_account(filename=json_file_path)

def get_sheet(i):
    doc = gc.open_by_url(spreadsheet_url)
    worksheet = doc.get_worksheet(i)

    return worksheet


def write_sheet(table):
    pass