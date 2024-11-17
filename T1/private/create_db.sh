#!/bin/bash
rm typeracer.db
sqlite3 typeracer.db < setup.sql
chmod 777 typeracer.db
