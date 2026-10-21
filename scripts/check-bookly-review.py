from pathlib import Path
root = Path("unirad-quick-booking-v2.php").read_text()
mirror = Path("unirad-quick-booking-v2/unirad-quick-booking-v2.php").read_text()
assert root == mirror, "Plugin copies diverged"
write = root.split("public static function write_to_bookly( $order_id )", 1)[1]
assert "SELECT GET_LOCK(%s, %d)" in write, "Missing staff advisory lock"
assert "SELECT RELEASE_LOCK(%s)" in write, "Missing lock release"
assert "start_date < %s AND end_date > %s" in write, "Missing interval overlap check"
assert "_uqb_owned_bookly_appt_id" in write, "Missing ownership marker"
assert "FORCE-updated existing row" not in write, "Unsafe unconditional overwrite remains"
remove = root.split("public static function remove_from_bookly( $order_id )", 1)[1].split("public static function write_to_bookly(", 1)[0]
assert remove.index("_uqb_owned_bookly_appt_id") < remove.index("$wpdb->delete("), "Delete not guarded by ownership"
send = root.split("public static function send_patient_confirmation( $order_id )", 1)[1].split("public static function ", 1)[0]
assert "_uqb_owned_bookly_appt_id" in send, "Confirmation missing ownership guard"
print("Static review guards passed; NOT an integration or concurrency test.")
