<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

// oxfmt-ignore
type Props = {
    passwordRules: string;
} ;

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: '/admin/dashboard',
            },
            {
                title: 'Reset Password',
                href: '/settings/security',
            },
        ],
    },
});
</script>

<template>
    <Head title="Reset Password" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <Heading
            title="Reset Password"
            description="Perbarui kata sandi akun Anda secara berkala untuk menjaga keamanan sistem."
        />

        <div class="max-w-xl rounded-xl border bg-card p-6 text-card-foreground shadow-xs">
            <Form
                v-bind="SecurityController.update.form()"
                :options="{
                    preserveScroll: true,
                }"
                reset-on-success
                :reset-on-error="[
                    'password',
                    'password_confirmation',
                    'current_password',
                ]"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="current_password">Password Saat Ini</Label>
                    <PasswordInput
                        id="current_password"
                        name="current_password"
                        class="mt-1 block w-full"
                        autocomplete="current-password"
                        placeholder="Masukkan password saat ini"
                    />
                    <InputError :message="errors.current_password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password Baru</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        class="mt-1 block w-full"
                        autocomplete="new-password"
                        placeholder="Masukkan password baru"
                        :passwordrules="props.passwordRules"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Konfirmasi Password Baru</Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        class="mt-1 block w-full"
                        autocomplete="new-password"
                        placeholder="Ulangi password baru"
                        :passwordrules="props.passwordRules"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <div class="flex items-center gap-4 pt-2">
                    <Button
                        :disabled="processing"
                        data-test="update-password-button"
                    >
                        Simpan Perubahan
                    </Button>
                </div>
            </Form>
        </div>
    </div>
</template>
