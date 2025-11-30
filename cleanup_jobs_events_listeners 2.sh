#!/bin/bash

# Remove all jobs (Cogni related)
cd app/Jobs
rm -f *.php

# Remove all events (live classes, messaging, collaboration)
cd ../Events
rm -f *.php

# Remove all listeners
cd ../Listeners
rm -f *.php

echo "Jobs, Events, and Listeners cleanup complete!"

