<?php
get_header();

$order_id = $_GET['orderId'] ?? '';
$reference_number = $_GET['referenceNumber'] ?? '';
$amount = $_GET['amount'] ?? '';
$status = $_GET['status'] ?? 'ناموفق';
?>

<style>
.payment-result {
    direction: rtl;
    width: 60%;
    margin: 50px auto;
    background: #fff;
    padding: 40px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 0 15px rgba(0,0,0,0.1);
    font-family: IRANSans, sans-serif;
}

.payment-result h2 {
    padding: 15px;
    border-radius: 12px;
    color: #fff;
    margin-bottom: 25px;
}

.payment-result.success h2 {
    background-color: #388d6a;
}

.payment-result.failed h2 {
    background-color: #f44336;
}

.payment-result table {
    width: 80%;
    margin: 0 auto 20px;
    border-collapse: collapse;
}

.payment-result th, .payment-result td {
    border: 1px solid #ccc;
    padding: 10px;
}

.payment-result th {
    background-color: #f2f2f2;
    text-align: right;
}

.btn-home {
    display: inline-block;
    padding: 10px 20px;
    background-color: #6A23FF;
    color: #fff;
    border-radius: 12px;
    text-decoration: none;
    font-family: inherit;
    margin-top: 15px;
}
</style>

<div class="payment-result <?php echo esc_attr($status === 'موفق' ? 'success' : 'failed'); ?>">
    <h2><?php echo esc_html($status === 'موفق' ? 'پرداخت با موفقیت انجام شد' : 'پرداخت ناموفق بود'); ?></h2>

    <table>
        <tr><th>شماره سفارش</th><td><?php echo esc_html($order_id); ?></td></tr>
        <tr><th>شماره مرجع</th><td><?php echo esc_html($reference_number); ?></td></tr>
        <tr><th>مبلغ سفارش (ریال)</th><td><?php echo esc_html($amount); ?></td></tr>
        <tr><th>وضعیت</th><td><?php echo esc_html($status); ?></td></tr>
    </table>

    <?php if ($status !== 'موفق'): ?>
        <p>در صورت کسر وجه از حساب شما، طی ۲۴ ساعت مبلغ بازگردانده می‌شود.</p>
    <?php endif; ?>

    <a href="<?php echo esc_url(home_url()); ?>" class="btn-home">بازگشت به صفحه اصلی</a>
</div>

<?php get_footer(); ?>
