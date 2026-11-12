"""Telkomsel voucher gateway.

A small internal service that fronts the public Telkomsel physical-voucher API
with a shared rate limiter, browser-grade TLS impersonation and on-demand
reCAPTCHA solving. Laravel calls this service instead of Telkomsel directly.
"""
